<?php

use App\Models\Payment;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\WaterProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('provider payment attachments use the shared preview modal', function (string $extension) {
    $payment = Payment::factory()->create(['proof_path' => 'payment-proofs/proof.'.$extension]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $payment->water_provider_id]);

    $this->actingAs($staff)->get(route('provider.payments.index'))->assertOk()
        ->assertSee('href="'.route('payments.proof', $payment).'" data-document-preview="attachment"', false)
        ->assertSee('data-modal="document-preview"', false);
})->with(['pdf', 'jpg', 'jpeg', 'png']);

test('provider payment proofs are served inline with their content type', function (string $extension, string $mimeType) {
    Storage::fake('local');
    $file = $extension === 'pdf'
        ? UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")
        : UploadedFile::fake()->image('proof.'.$extension);
    $path = $file->store('payment-proofs', 'local');
    $payment = Payment::factory()->create(['proof_path' => $path]);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $payment->water_provider_id]);

    $response = $this->actingAs($staff)->get(route('payments.proof', ['payment' => $payment, 'preview' => 1]))
        ->assertOk()->assertHeader('Content-Type', $mimeType);

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
    expect($response->streamedContent())->toBe(Storage::disk('local')->get($path));
})->with([
    ['pdf', 'application/pdf'],
    ['jpg', 'image/jpeg'],
    ['jpeg', 'image/jpeg'],
    ['png', 'image/png'],
]);

test('payment attachment previews retain provider scope and payment permissions', function () {
    Storage::fake('local');
    Storage::disk('local')->put('payment-proofs/private.pdf', '%PDF-1.4 private proof');
    $payment = Payment::factory()->create(['proof_path' => 'payment-proofs/private.pdf']);
    $staff = User::factory()->waterProvider()->create(['water_provider_id' => $payment->water_provider_id]);
    $otherStaff = User::factory()->waterProvider()->create(['water_provider_id' => WaterProvider::factory()->create()->id]);
    $client = User::factory()->create(['consumer_id' => $payment->consumer_id]);
    $url = route('payments.proof', ['payment' => $payment, 'preview' => 1]);

    $this->actingAs($otherStaff)->get($url)->assertNotFound();
    $this->actingAs($client)->get($url)->assertForbidden();
    $this->actingAs($staff)->get($url)->assertOk();

    RolePermission::create(['role' => $staff->role->value, 'permission' => 'payments.view', 'allowed' => false]);
    $this->get($url)->assertForbidden();
});
