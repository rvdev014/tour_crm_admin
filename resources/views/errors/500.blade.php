@include('errors.layout', [
    'code' => '500 · Server Error',
    'title' => 'Something went wrong on our end',
    'message' => $message ?? 'An unexpected error occurred. Our team has been notified — please try again, or contact support with the error ID below.',
    'errorId' => $errorId ?? null,
])
