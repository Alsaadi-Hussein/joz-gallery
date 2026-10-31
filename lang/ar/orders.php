<?php

return [

    'singular' => 'طلب',
    'plural' => 'الطلبات',

    'telegram' => [
        'title' => '🛒 طلب جديد :number',
        'name' => 'الاسم: :value',
        'phone' => 'الهاتف: :value',
        'address' => 'العنوان: :governorate / :area',
        'landmark' => 'أقرب نقطة دالة: :value',
        'notes' => 'ملاحظات: :value',
        'total' => 'المجموع الكلي: :value',
    ],

    'email' => [
        'subject' => 'طلب جديد :number',
        'heading' => 'وصل طلب جديد',
        'open' => 'فتح الطلب في لوحة التحكم',
    ],

    'fields' => [
        'number' => 'رقم الطلب',
        'date' => 'التاريخ',
        'name' => 'الاسم',
        'phone' => 'الهاتف',
        'governorate' => 'المحافظة',
        'area' => 'المنطقة',
        'landmark' => 'أقرب نقطة دالة',
        'notes' => 'ملاحظات',
        'coupon' => 'الكوبون',
        'status' => 'الحالة',
        'subtotal' => 'المجموع',
        'discount' => 'الخصم',
        'delivery_fee' => 'أجور التوصيل',
        'total' => 'المجموع الكلي',
        'items' => 'القطع',
        'item' => 'القطعة',
        'variant' => 'الخيار',
        'unit_price' => 'السعر',
        'qty' => 'الكمية',
        'line_total' => 'الإجمالي',
        'locale' => 'لغة الطلب',
        'from' => 'من تاريخ',
        'until' => 'إلى تاريخ',
    ],

    'sections' => [
        'customer' => 'بيانات الزبون',
        'items' => 'القطع المطلوبة',
        'totals' => 'المبالغ',
    ],

    'actions' => [
        'change_status' => 'تغيير الحالة',
        'status_changed' => 'تم تغيير حالة الطلب',
        'cancel_warning' => 'الإلغاء نهائي: تعود الكميات إلى المخزون ويعود استخدام الكوبون، ولا يمكن تغيير حالة الطلب بعدها.',
        'invoice' => 'طباعة الفاتورة',
        'export' => 'تصدير إلى Excel',
        'export_done' => 'اكتمل تصدير :count طلب.',
        'export_failed' => 'تعذر تصدير :count طلب.',
    ],

    'invoice' => [
        'title' => 'فاتورة',
        'print' => 'طباعة',
        'bill_to' => 'إلى',
        'payment' => 'طريقة الدفع',
        'cash' => 'نقداً عند الاستلام',
        'thanks' => 'شكراً لتسوقكم من جوز كاليري للمنزل',
    ],

];
