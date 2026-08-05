<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use JsonException;

final class CaptureCashPaymentFingerprint
{
    private const int VERSION = 1;

    private const string ACTION = 'payments.capture_cash_payment';

    /**
     * @throws JsonException
     */
    public function forCommand(CaptureCashPaymentCommand $command): string
    {
        return hash('sha256', $this->canonicalPayload($command));
    }

    /**
     * @throws JsonException
     */
    public function canonicalPayload(CaptureCashPaymentCommand $command): string
    {
        return json_encode([
            'version' => self::VERSION,
            'action' => self::ACTION,
            'order_id' => $command->orderId,
            'cashbox_id' => $command->cashboxId,
            'expected_amount_minor' => $command->expectedAmountMinor,
            'expected_currency' => $command->expectedCurrency,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
