@if (session('status'))
    <div data-flash-status="{{ e(session('status')) }}" hidden></div>
@endif

@if (isset($errors) && $errors->any())
    <script type="application/json" id="portal-validation-payload">
        {!! json_encode([
            'title' => $validationErrorTitle ?? 'Please fix the following',
            'errors' => $errors->all(),
        ]) !!}
    </script>
@endif
