<?php
/** شارة الحالة - تعتمد على $v['status'] */
$statusMap = [
    'open' => ['مفتوح', 'open'],
    'assigned' => ['مكلف', 'assigned'],
    'ended' => ['منتهي', 'ended'],
    'cancelled' => ['ملغي', 'cancelled'],
];
[$label, $cls] = $statusMap[$v['status']] ?? [$v['status'], 'open'];
?>
<span class="badge badge-<?= e($cls) ?>"><?= e($label) ?></span>
