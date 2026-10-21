# Authentication Flow

TaskPad uses **Laravel Sanctum** for stateless API token authentication. This document describes the full register, login, and logout flows.

---

## Registration Flow

```
Client                          API
  │                              │
  │  POST /api/register          │
  │  { name, email,              │
  │    password,                 │
  │    confirmpassword }         │
  │ ─────────────────────────► │
  │                              │
  │                    RegisterRequest validates input
  │                    (required fields, email unique, password match)
  │                              │
  │                    User::create() — password hashed via bcrypt
  │                              │
  │                    Token generated via Sanctum
  │                              │
  │  ◄──────────────────────── │
  │  200 { status: "true",       │
  │    message: "user created.", │
  │    token: "1|abc123..." }    │
  │                              │
```

---

## Login Flow

```
Client                          API
  │                              │
  │  POST /api/login             │
  │  { email, password }         │
  │ ─────────────────────────► │
  │                              │
  │                    LoginRequest validates input
  │                              │
  │                    User looked up by email
  │                    Hash::check(password, user.password)
  │                              │
  │              ┌──── if credentials fail ────┐
  │              │                             │
  │  ◄─── 401 { status: "false",              │
  │         message: "Invalid credentials." }  │
  │              │                             │
  │              └─────────────────────────────┘
  │                              │
  │                    Token generated via Sanctum
  │                    Previous tokens optionally revoked
  │                              │
  │  ◄──────────────────────── │
  │  200 { status: "true",       │
  │    message: "Login success.",│
  │    data: { name, email } }   │
  │  Set-Cookie: taskpad_token=..│
  │                              │
```

---

## Authenticated Request Flow

Once the client has a token, it must include it in every request header:

```
Authorization: Bearer 2|xyz789...
```

```
Client                          API
  │                              │
  │  GET /api/me                 │
  │  Authorization: Bearer <token>
  │ ─────────────────────────► │
  │                              │
  │                    Sanctum middleware resolves
  │                    the token and sets auth()->user()
  │                              │
  │              ┌──── if token invalid/expired ────┐
  │              │                                  │
  │  ◄─── 401 { message: "Unauthenticated." }      │
  │              │                                  │
  │              └──────────────────────────────────┘
  │                              │
  │                    Controller runs with
  │                    authenticated user context
  │                              │
  │  ◄──────────────────────── │
  │  200 { data: { user } }      │
  │                              │
```

---

## Logout Flow

```
Client                          API
  │                              │
  │  POST /api/logout            │
  │  Authorization: Bearer <token>
  │ ─────────────────────────► │
  │                              │
  │                    auth()->user()->currentAccessToken()->delete()
  │                    (Revokes only the current token)
  │                              │
  │  ◄──────────────────────── │
  │  200 { status: "true",       │
  │    message: "Logged out." }  │
  │                              │
```

---

## Token Storage & Transmission

TaskPad secures authentication by issuing the Sanctum token inside an `HttpOnly`, `SameSite=Lax` cookie (`taskpad_token`) alongside the JSON response.

- **Web / SPA (React)**: Requests include `withCredentials: true`. The browser automatically stores and transmits the `HttpOnly` cookie. JavaScript has no access to the cookie, mitigating XSS token theft.
- **Mobile / Third-Party**: Can continue to send the token in the `Authorization: Bearer <token>` header.

---

## Implementation Notes

- **Install Sanctum**: `composer require laravel/sanctum`
- **Run migration**: `php artisan migrate` (adds `personal_access_tokens` table)
- **Use `Hash::make()`** when creating users — never store plain-text passwords.
- **Use `Hash::check()`** on login — never compare passwords directly.
- **Scope tokens**: Sanctum supports token abilities (scopes). For TaskPad, start with a single `*` ability and add scopes later if needed.

### Token Generation Example

```php
// On successful login
$token = $user->createToken('taskpad')->plainTextToken;
$cookie = cookie('taskpad_token', $token, 60 * 24 * 7, '/', null, config('app.env') === 'production', true, false, 'Lax');

return response()->json([
    'status' => 'true',
    'message' => 'Login success.',
    'data' => [
        'name' => $user->name,
        'email' => $user->email,
    ],
])->withCookie($cookie);
```

### Password Verification Example

```php
if (!Hash::check($password, $user->password)) {
    return response()->json([
        'status' => 'false',
        'message' => 'Invalid credentials.',
    ], 401);
}
```
