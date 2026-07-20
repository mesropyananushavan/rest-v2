<?php

declare(strict_types=1);
?>

<div class="overflow-x-auto">
    <table {{ $attributes->class(['min-w-full divide-y divide-slate-100 text-left text-sm [&_tbody_tr:hover]:bg-smartrest-table-hover [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_th]:px-4 [&_th]:py-3 [&_th]:text-xs [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-[0.12em] [&_th]:text-slate-500']) }}>
        {{ $slot }}
    </table>
</div>
