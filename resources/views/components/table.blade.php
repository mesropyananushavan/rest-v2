<?php

declare(strict_types=1);
?>

<div class="table-responsive">
    <table {{ $attributes->class(['table sr-dense-table mb-0 align-middle']) }}>
        {{ $slot }}
    </table>
</div>
