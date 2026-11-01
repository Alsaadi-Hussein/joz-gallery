// End-to-end load test for the Joz Gallery store. It drives the same requests a
// browser makes: pages, the cart and checkout JSON calls, real orders, order
// tracking, and the admin login through Livewire.
//
//   k6 run -e BASE_URL=http://127.0.0.1:8080 -e PROFILE=smoke tests/load/store.k6.js
//
// Settings, all passed with -e:
//   BASE_URL        The site to test. Required.
//   PROFILE         smoke (1 min), full (the staged test) or capacity (finds the limit gently).
//   ALLOW_WRITES    1 places real orders. Without it the test only reads.
//   I_OWN_THIS_HOST yes, required for writes on anything but localhost or a staging host.
//   SPOOF_IPS       1 gives each virtual user its own address through X-Forwarded-For.
//                   Only works with TRUSTED_PROXIES=* and is for a bench, never production:
//                   otherwise every user shares one address and the rate limits stop them.
//   ADMIN_EMAIL, ADMIN_PASSWORD   A test admin. Without them the login flow is skipped.
//   STEPS, BUCKET   For capacity: the user counts to climb through, and how finely to
//                   report them. STEPS=20,40,60 BUCKET=10 finds the limit of a small server.

import { check, fail, sleep } from 'k6';
import exec from 'k6/execution';
import http from 'k6/http';
import { Counter, Rate } from 'k6/metrics';

const BASE = (__ENV.BASE_URL || '').replace(/\/$/, '');
const PROFILE = __ENV.PROFILE || 'smoke';
const WRITES = __ENV.ALLOW_WRITES === '1';
const SPOOF = __ENV.SPOOF_IPS === '1';
const ADMIN = __ENV.ADMIN_EMAIL && __ENV.ADMIN_PASSWORD;

const P95_LIMIT_MS = 500;
const FAILURE_LIMIT = 0.01;
const LOAD_BUCKET = Number(__ENV.BUCKET || 100);
const HIGHEST_BUCKET = LOAD_BUCKET * 15;
const CAPACITY_STEPS = (__ENV.STEPS || '50,100,150,200,250,300,400,500').split(',').map(Number);

const ordersPlaced = new Counter('orders_placed');
const ordersRefused = new Counter('orders_refused');
const throttled = new Rate('throttled');
const flowOk = new Rate('flow_ok');
const reported429 = {};

// Counted as success, so a surprise here must never pass unnoticed.
function noteThrottle(response, name) {
    const hit = response.status === 429;

    throttled.add(hit);

    if (hit && !reported429[name]) {
        reported429[name] = true;
        console.warn(`429 on "${name}" for user ${exec.vu.idInTest}`);
    }
}

const SCENARIOS = {
    smoke: {
        warmup: { executor: 'constant-vus', vus: 5, duration: '1m' },
    },
    full: {
        warmup: { executor: 'constant-vus', vus: 10, duration: '1m' },
        peak: {
            executor: 'ramping-vus',
            startTime: '1m',
            startVUs: 100,
            gracefulRampDown: '10s',
            stages: [
                { target: 100, duration: '1m' },
                { target: 300, duration: '2m' },
                { target: 500, duration: '2m' },
            ],
        },
        stress: {
            executor: 'ramping-vus',
            startTime: '6m10s',
            startVUs: 500,
            gracefulRampDown: '10s',
            stages: [
                { target: 1000, duration: '1m30s' },
                { target: 1500, duration: '1m30s' },
                { target: 1500, duration: '30s' },
                { target: 0, duration: '30s' },
            ],
        },
    },
    // Climbs slowly in steps, so each level settles and the limit shows clearly.
    capacity: {
        warmup: { executor: 'constant-vus', vus: 10, duration: '30s' },
        peak: {
            executor: 'ramping-vus',
            startTime: '30s',
            startVUs: 10,
            gracefulRampDown: '10s',
            stages: CAPACITY_STEPS.flatMap((target) => [
                { target, duration: '20s' },
                { target, duration: '40s' },
            ]),
        },
    },
};

const thresholds = {
    'http_req_failed{scenario:warmup}': [`rate<${FAILURE_LIMIT}`],
    'http_req_duration{scenario:warmup}': [`p(95)<${P95_LIMIT_MS}`],
    'flow_ok{scenario:warmup}': ['rate>0.99'],
};

if (PROFILE === 'full' || PROFILE === 'capacity') {
    thresholds['http_req_failed{scenario:peak}'] = [`rate<${FAILURE_LIMIT}`];
    thresholds['http_req_duration{scenario:peak}'] = [`p(95)<${P95_LIMIT_MS}`];
    thresholds['flow_ok{scenario:peak}'] = ['rate>0.99'];
}

if (PROFILE === 'full') {
    // Recorded, never failed on: this stage exists to find where the site breaks.
    thresholds['http_req_failed{scenario:stress}'] = ['rate<=1'];
    thresholds['http_req_duration{scenario:stress}'] = ['p(95)<999999'];
}

// A threshold is what makes k6 report a tagged metric, so these only collect figures.
for (let load = LOAD_BUCKET; load <= HIGHEST_BUCKET; load += LOAD_BUCKET) {
    thresholds[`http_req_duration{load:${load}}`] = ['p(95)<999999'];
    thresholds[`http_req_failed{load:${load}}`] = ['rate<=1'];
}

for (const flow of ['browse', 'cart', 'checkout', 'track', 'admin']) {
    thresholds[`http_req_duration{flow:${flow}}`] = ['p(95)<999999'];
    thresholds[`http_req_failed{flow:${flow}}`] = ['rate<=1'];
}

export const options = {
    scenarios: SCENARIOS[PROFILE],
    thresholds,
    summaryTrendStats: ['count', 'avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
    discardResponseBodies: false,
    noVUConnectionReuse: false,
    insecureSkipTLSVerify: false,
};

// A 429 is the site protecting itself, not a failure, and is counted on its own.
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }, 429));

export function setup() {
    if (!BASE) {
        fail('Set BASE_URL, for example -e BASE_URL=http://127.0.0.1:8080');
    }

    if (!SCENARIOS[PROFILE]) {
        fail(`Unknown PROFILE "${PROFILE}"`);
    }

    const safeHost = /^https?:\/\/(127\.0\.0\.1|localhost|staging\.)/.test(BASE);

    if (WRITES && !safeHost && __ENV.I_OWN_THIS_HOST !== 'yes') {
        fail(`ALLOW_WRITES places real orders. Refusing on ${BASE} without I_OWN_THIS_HOST=yes.`);
    }

    const home = http.get(`${BASE}/`);

    if (home.status !== 200) {
        fail(`The home page returned ${home.status}`);
    }

    const unique = (body, pattern) => [...new Set([...body.matchAll(pattern)].map((match) => match[1]))];
    const products = unique(home.body, /\/product\/([a-z0-9-]+)"/g);
    const categories = unique(home.body, /\/category\/([a-z0-9-]+)"/g);
    const buyable = unique(home.body, /data-add="(\d+)"/g);
    const governorates = unique(http.get(`${BASE}/checkout`).body, /<option value="(\d+)"/g);

    if (!products.length || !buyable.length || !governorates.length) {
        fail('Could not find products or governorates on the site');
    }

    return { products, categories, buyable, governorates };
}

function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

function think(min = 1, max = 3) {
    sleep(min + Math.random() * (max - min));
}

function headers() {
    if (!SPOOF) {
        return {};
    }

    const vu = exec.vu.idInTest;

    return { 'X-Forwarded-For': `10.${(vu >> 16) & 255}.${(vu >> 8) & 255}.${vu & 255}` };
}

function get(path, name, flow) {
    return http.get(`${BASE}${path}`, { headers: headers(), tags: { name, flow } });
}

function csrf(response) {
    const match = response.body && response.body.match(/name="csrf-token" content="([^"]+)"/);

    return match ? match[1] : '';
}

function postJson(path, body, token, name, flow) {
    const response = http.post(`${BASE}${path}`, JSON.stringify(body), {
        headers: { ...headers(), 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
        tags: { name, flow },
    });

    noteThrottle(response, name);

    return response;
}

function line(data) {
    return [{ product_id: Number(pick(data.buyable)), variant_id: null, qty: 1 }];
}

function browse(data) {
    const flow = 'browse';
    const home = get('/', 'home', flow);
    let ok = check(home, { 'home opens': (r) => r.status === 200 && r.body.includes('csrf-token') });
    think();

    if (data.categories.length) {
        ok = check(get(`/category/${pick(data.categories)}?sort=price_asc`, 'category', flow), { 'category opens': (r) => r.status === 200 }) && ok;
        think();
    }

    ok = check(get(`/product/${pick(data.products)}`, 'product', flow), { 'product opens': (r) => r.status === 200 }) && ok;
    think();

    get(`/search/suggest?q=${encodeURIComponent('كن')}`, 'search suggest', flow);
    ok = check(get(`/search?q=${encodeURIComponent('كنبة')}`, 'search', flow), { 'search opens': (r) => [200, 429].includes(r.status) }) && ok;
    flowOk.add(ok);
}

function cart(data) {
    const flow = 'cart';
    const page = get('/cart', 'cart page', flow);
    const token = csrf(page);
    think();

    const preview = postJson('/cart/preview', { items: line(data) }, token, 'cart preview', flow);
    let ok = check(preview, { 'cart preview answers': (r) => r.status === 429 || (r.status === 200 && r.json('count') === 1) });
    think();

    const quote = postJson('/checkout/quote', { items: line(data), governorate_id: Number(pick(data.governorates)), coupon: null }, token, 'checkout quote', flow);
    ok = check(quote, { 'quote answers': (r) => r.status === 429 || (r.status === 200 && Boolean(r.json('total_formatted'))) }) && ok;
    flowOk.add(ok);
}

function checkout(data) {
    if (!WRITES) {
        return cart(data);
    }

    const flow = 'checkout';
    const page = get('/checkout', 'checkout page', flow);
    think(2, 5);

    const phone = `0770${String(Math.floor(Math.random() * 1e7)).padStart(7, '0')}`;
    const response = http.post(`${BASE}/checkout`, {
        _token: csrf(page),
        name: 'زبون اختبار الحمل',
        phone,
        governorate_id: pick(data.governorates),
        area: 'المنصور',
        landmark: 'قرب الجامع',
        notes: 'load test',
        coupon: '',
        cart: JSON.stringify(line(data)),
    }, { headers: headers(), tags: { name: 'place order', flow } });

    noteThrottle(response, 'place order');

    if (response.status === 429) {
        return flowOk.add(true);
    }

    const placed = response.status === 200 && /\/order\/[^/]+\/thanks/.test(response.url);

    (placed ? ordersPlaced : ordersRefused).add(1);

    const ok = check(response, { 'order placed': () => placed });
    flowOk.add(ok);

    if (!placed) {
        return undefined;
    }

    think();

    const number = (response.body || '').match(/JG-\d{2}-\d+/);

    if (number) {
        track(number[0], phone);
    }

    return undefined;
}

function track(number, phone) {
    const flow = 'track';
    const page = get('/track', 'track page', flow);
    think();

    const response = http.post(`${BASE}/track`, { _token: csrf(page), number, phone }, { headers: headers(), tags: { name: 'track order', flow } });

    noteThrottle(response, 'track order');
    flowOk.add(check(response, { 'tracking answers': (r) => [200, 429].includes(r.status) }));
}

function adminSession() {
    if (!ADMIN) {
        return;
    }

    const flow = 'admin';
    const page = get('/admin/login', 'admin login page', flow);
    const body = page.body || '';
    const update = body.match(/data-update-uri="([^"]+)"/);
    const token = body.match(/data-csrf="([^"]+)"/) || body.match(/name="csrf-token" content="([^"]+)"/);
    const snapshots = [...body.matchAll(/wire:snapshot="([^"]+)"/g)].map((match) => match[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&'));
    const snapshot = snapshots.find((value) => value.includes('"email"'));

    if (!update || !token || !snapshot) {
        return flowOk.add(false);
    }

    think();

    const login = http.post(update[1], JSON.stringify({
        _token: token[1],
        components: [{
            snapshot,
            updates: { 'data.email': __ENV.ADMIN_EMAIL, 'data.password': __ENV.ADMIN_PASSWORD },
            calls: [{ path: '', method: 'authenticate', params: [] }],
        }],
    }), {
        headers: { ...headers(), 'Content-Type': 'application/json', 'X-Livewire': '1' },
        tags: { name: 'admin login', flow },
    });

    noteThrottle(login, 'admin login');

    const signedIn = check(login, { 'admin signed in': (r) => r.status === 200 && (r.body || '').includes('redirect') });

    if (signedIn) {
        think();
        flowOk.add(check(get('/admin/orders', 'admin orders', flow), { 'orders list opens': (r) => r.status === 200 && r.url.includes('/admin/orders') }));
    } else {
        flowOk.add(false);
    }

    return undefined;
}

export default function shopper(data) {
    const load = Math.min(HIGHEST_BUCKET, Math.max(LOAD_BUCKET, Math.ceil(exec.instance.vusActive / LOAD_BUCKET) * LOAD_BUCKET));

    exec.vu.metrics.tags.load = String(load);

    const roll = Math.random();

    if (roll < 0.70) {
        browse(data);
    } else if (roll < 0.85) {
        cart(data);
    } else if (roll < 0.95) {
        checkout(data);
    } else if (roll < 0.99) {
        track('JG-26-0001', '07701234567');
    } else {
        adminSession();
    }

    think();
}

function stat(data, name, value) {
    const metric = data.metrics[name];

    return metric && metric.values ? metric.values[value] : undefined;
}

function row(label, data, durationName, failedName) {
    const p95 = stat(data, durationName, 'p(95)');
    const requests = stat(data, durationName, 'count') ?? 0;

    if (p95 === undefined || requests === 0) {
        return null;
    }

    const failed = stat(data, failedName, 'rate') ?? 0;

    return {
        label,
        p50: stat(data, durationName, 'med'),
        p95,
        p99: stat(data, durationName, 'p(99)'),
        failed,
        requests,
        ok: p95 < P95_LIMIT_MS && failed < FAILURE_LIMIT,
    };
}

function table(title, rows) {
    const lines = [`\n${title}`, '  label                     p50 ms    p95 ms    p99 ms   failed'];

    rows.filter(Boolean).forEach((r) => {
        const cell = (value) => String(Math.round(value)).padStart(8);

        lines.push(`  ${r.label.padEnd(22)} ${cell(r.p50)}  ${cell(r.p95)}  ${cell(r.p99)}  ${(r.failed * 100).toFixed(2).padStart(6)}%  ${r.ok ? '' : '<-- over target'}`);
    });

    return lines.join('\n');
}

export function handleSummary(data) {
    const stages = ['warmup', 'peak', 'stress'].map((stage) => row(stage, data, `http_req_duration{scenario:${stage}}`, `http_req_failed{scenario:${stage}}`));
    const flows = ['browse', 'cart', 'checkout', 'track', 'admin'].map((flow) => row(flow, data, `http_req_duration{flow:${flow}}`, `http_req_failed{flow:${flow}}`));
    const levels = [];

    for (let load = LOAD_BUCKET; load <= HIGHEST_BUCKET; load += LOAD_BUCKET) {
        levels.push(row(`up to ${load} users`, data, `http_req_duration{load:${load}}`, `http_req_failed{load:${load}}`));
    }

    const seen = levels.filter(Boolean);
    const firstBad = seen.find((level) => !level.ok);
    const lastGood = [...seen].reverse().find((level) => level.ok && (!firstBad || seen.indexOf(level) < seen.indexOf(firstBad)));
    const warm = stages[0];
    const peak = stages[1];

    const verdict = [
        `\nRequests: ${stat(data, 'http_reqs', 'count') ?? 0} at ${(stat(data, 'http_reqs', 'rate') ?? 0).toFixed(1)}/s`,
        `Orders placed: ${stat(data, 'orders_placed', 'count') ?? 0}, refused: ${stat(data, 'orders_refused', 'count') ?? 0}`,
        `Throttled (429): ${(((stat(data, 'throttled', 'rate') ?? 0) * 100)).toFixed(2)}% of write calls`,
        `Flows completed: ${(((stat(data, 'flow_ok', 'rate') ?? 0) * 100)).toFixed(2)}%`,
        warm && peak ? `Slowdown from warm-up to peak: ${(peak.p95 / warm.p95).toFixed(1)}x at p95` : '',
        firstBad ? `Breaking point: ${firstBad.label} (p95 ${Math.round(firstBad.p95)} ms, ${(firstBad.failed * 100).toFixed(2)}% failed)` : 'Breaking point: not reached',
        lastGood ? `Highest level inside targets: ${lastGood.label}` : 'Highest level inside targets: none',
    ].filter(Boolean).join('\n');

    const text = [
        table('By stage', stages),
        table('By user flow', flows),
        table('By concurrent users', levels),
        verdict,
        '',
    ].join('\n');

    return {
        stdout: text,
        [`tests/load/results/${PROFILE}-summary.txt`]: text,
        [`tests/load/results/${PROFILE}-summary.json`]: JSON.stringify(data, null, 2),
    };
}
