@once
    <x-auth-session-status :status="session('status')" />

    @if ($errors->any())
        <x-feedback-toast :text="implode(' ', $errors->all())" variant="danger" :duration="0" data-form-feedback />
    @endif
@endonce
