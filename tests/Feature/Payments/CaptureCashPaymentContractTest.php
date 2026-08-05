<?php

declare(strict_types=1);

use App\Modules\Payments\Application\CaptureCashPaymentCommand;
use App\Modules\Payments\Application\CaptureCashPaymentFingerprint;
use App\Modules\Payments\Application\CaptureCashPaymentResult;
use App\Modules\Payments\Domain\PaymentsDomainException;

it('defines the approved cash payment capture command and result contracts', function (): void {
    $command = new CaptureCashPaymentCommand(
        orderId: 101,
        cashboxId: 202,
        expectedAmountMinor: 3_500,
        expectedCurrency: 'AMD',
        idempotencyKey: 'cash-capture-key',
    );

    $result = new CaptureCashPaymentResult(
        paymentId: 1,
        paymentAllocationId: 2,
        cashboxEntryId: 3,
        tenantId: 4,
        branchId: 5,
        orderId: $command->orderId,
        cashboxId: $command->cashboxId,
        amountMinor: $command->expectedAmountMinor,
        currency: $command->expectedCurrency,
        idempotencyKey: $command->idempotencyKey,
        idempotencyFingerprint: hash('sha256', 'canonical'),
        replayed: false,
    );

    expect(get_object_vars($command))->toBe([
        'orderId' => 101,
        'cashboxId' => 202,
        'expectedAmountMinor' => 3_500,
        'expectedCurrency' => 'AMD',
        'idempotencyKey' => 'cash-capture-key',
    ])->and($result->paymentId)->toBe(1)
        ->and($result->paymentAllocationId)->toBe(2)
        ->and($result->cashboxEntryId)->toBe(3)
        ->and($result->tenantId)->toBe(4)
        ->and($result->branchId)->toBe(5)
        ->and($result->orderId)->toBe(101)
        ->and($result->cashboxId)->toBe(202)
        ->and($result->amountMinor)->toBe(3_500)
        ->and($result->currency)->toBe('AMD')
        ->and($result->idempotencyKey)->toBe('cash-capture-key')
        ->and($result->idempotencyFingerprint)->toBe(hash('sha256', 'canonical'))
        ->and($result->replayed)->toBeFalse();
});

it('builds the canonical idempotency fingerprint from the approved capture inputs', function (): void {
    $command = new CaptureCashPaymentCommand(
        orderId: 10,
        cashboxId: 20,
        expectedAmountMinor: 9_999,
        expectedCurrency: 'AMD',
        idempotencyKey: 'not-part-of-fingerprint',
    );

    $fingerprint = new CaptureCashPaymentFingerprint;
    $canonicalPayload = '{"version":1,"action":"payments.capture_cash_payment","order_id":10,"cashbox_id":20,"expected_amount_minor":9999,"expected_currency":"AMD"}';

    expect($fingerprint->canonicalPayload($command))->toBe($canonicalPayload)
        ->and($fingerprint->forCommand($command))->toBe(hash('sha256', $canonicalPayload));
});

it('keeps idempotency keys out of the canonical payment capture fingerprint', function (): void {
    $fingerprint = new CaptureCashPaymentFingerprint;

    $first = new CaptureCashPaymentCommand(10, 20, 9_999, 'AMD', 'first-key');
    $samePayloadDifferentKey = new CaptureCashPaymentCommand(10, 20, 9_999, 'AMD', 'second-key');
    $differentAmount = new CaptureCashPaymentCommand(10, 20, 10_000, 'AMD', 'first-key');
    $differentCurrency = new CaptureCashPaymentCommand(10, 20, 9_999, 'USD', 'first-key');

    expect($fingerprint->forCommand($first))->toBe($fingerprint->forCommand($samePayloadDifferentKey))
        ->and($fingerprint->forCommand($first))->not->toBe($fingerprint->forCommand($differentAmount))
        ->and($fingerprint->forCommand($first))->not->toBe($fingerprint->forCommand($differentCurrency));
});

it('defines stable translated payment capture domain errors for the next action slice', function (): void {
    $factories = [
        fn (): PaymentsDomainException => PaymentsDomainException::actorContextRequired(),
        fn (): PaymentsDomainException => PaymentsDomainException::captureAmountMustBePositive(),
        fn (): PaymentsDomainException => PaymentsDomainException::captureCurrencyInvalid(),
        fn (): PaymentsDomainException => PaymentsDomainException::idempotencyKeyRequired(),
        fn (): PaymentsDomainException => PaymentsDomainException::idempotencyKeyTooLong(),
        fn (): PaymentsDomainException => PaymentsDomainException::idempotencyKeyWhitespace(),
        fn (): PaymentsDomainException => PaymentsDomainException::idempotencyKeyControlCharacters(),
        fn (): PaymentsDomainException => PaymentsDomainException::idempotencyConflict(),
        fn (): PaymentsDomainException => PaymentsDomainException::cashboxUnavailable(),
        fn (): PaymentsDomainException => PaymentsDomainException::expectedAmountMismatch(),
        fn (): PaymentsDomainException => PaymentsDomainException::expectedCurrencyMismatch(),
        fn (): PaymentsDomainException => PaymentsDomainException::orderAlreadyFullyPaid(),
        fn (): PaymentsDomainException => PaymentsDomainException::orderOverAllocated(),
    ];

    $codes = collect($factories)
        ->map(fn (Closure $factory): string => $factory()->errorCode())
        ->all();

    expect($codes)->toBe([
        'payments.actor_context_required',
        'payments.capture_amount_must_be_positive',
        'payments.capture_currency_invalid',
        'payments.idempotency_key_required',
        'payments.idempotency_key_too_long',
        'payments.idempotency_key_whitespace',
        'payments.idempotency_key_control_characters',
        'payments.idempotency_conflict',
        'payments.cashbox_unavailable',
        'payments.expected_amount_mismatch',
        'payments.expected_currency_mismatch',
        'payments.order_already_fully_paid',
        'payments.order_over_allocated',
    ]);

    foreach (['en', 'hy', 'ru'] as $locale) {
        app()->setLocale($locale);

        foreach ($codes as $code) {
            expect(__($code))->not->toBe($code);
        }
    }
});
