@include('errors.layout', [
    'code' => '429 · Too Many Requests',
    'title' => "You're going a bit fast",
    'message' => 'Too many requests were sent in a short time. Please wait a moment and try again.',
])
