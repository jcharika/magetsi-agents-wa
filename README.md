<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


 magetsi-agents  php artisan whatsapp:generate-keypair
✅ Key pair generated successfully!

+-------------+---------------------------------------------------------------------------------+
| Key         | Path                                                                            |
+-------------+---------------------------------------------------------------------------------+
| Private Key | /Users/chareka/dev/src/chatbots/magetsi-agents/storage/app/whatsapp/private.pem |
| Public Key  | /Users/chareka/dev/src/chatbots/magetsi-agents/storage/app/whatsapp/public.pem  |
+-------------+---------------------------------------------------------------------------------+

⚠️  Next steps:
1. Set WHATSAPP_FLOW_PRIVATE_KEY_PATH in .env:
   WHATSAPP_FLOW_PRIVATE_KEY_PATH=/Users/chareka/dev/src/chatbots/magetsi-agents/storage/app/whatsapp/private.pem

2. Upload the public key to Meta:

   curl -X POST \
   'https://graph.facebook.com/v21.0/116331474411796/whatsapp_business_encryption' \
   -H 'Authorization: Bearer <ACCESS_TOKEN>' \
   -H 'Content-Type: application/x-www-form-urlencoded' \
   -d 'business_public_key=-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA9S5e2WoPv43a8GOjGm7F\nJy0S0hKqpWLydQveroBWVYKEZEpRwImMvGYySgcIjSz0W6QW1Mm+94pnJ0Hx4ilP\n6FhSC/laD8/ze0yrbVO8ucLLRfXjVjwTsVYPIMTFdAVQfQ6IAz3/4UeRB/3ujUSB\nYi4FXgyN8txGw6MFjgBVaBjnihLOso0cn3Qlnj2gsNvR1qhyFh1WtI42b+xOM4wW\nI8LTEm6fhch9N7d1rBKbznY8ZkQS5vEHqhq7jhsHxCGuTHGeBl1oVpehCw/WYEgI\nhYe13XCq2KNxiWH6ZLx7n0N31fumR94U7pzr00qxnX0SoD82a24+EBu9NoEC85M5\nFQIDAQAB\n-----END PUBLIC KEY-----'

3. See docs/whatsapp-flows-setup.md for full instructions.
    chareka   magetsi-agents  main ≢  ?3 ~13  +5                                                                           in zsh at 21:38:32


curl -X POST \
'https://graph.facebook.com/v25.0/116331474411796/whatsapp_business_encryption' \
-H 'Authorization: Bearer EAALpAH695ugBRKYmb5rBsOylHbcZAb9nO5xBqsokLUXDw2I4hBgCkbsuvnbmKsCJaMiWqyUdot9ht79u7BhhczoAzlP6WilFpYvC5e6hL5KTUu5XrRYgGLgE2s5oUTHnhDWEtSA2A20z9u5GZClGZCd9wX67tmECJYTNljT4MJevhpRpIYKLzZBTeVwY6bGubOexYyHZBxekLLFgChiQgtrxiDegvtWhyjPuNglCGZBX34ZBiMYf8lw5mPVxSO8fQUsjeoWm9wG1qK2R6Gd9FiaTuPMRgZDZD' \
-H 'Content-Type: application/x-www-form-urlencoded' \
--data-urlencode 'business_public_key=-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA7xJJJhGUzF8gqnnbxmXl
1FHO3jkZ3qc7l6qlbNrW3FJAC9UOJmw27Lbi2Iq+Espw1V8RFuamT6YsnV3kRwkg
PTOZHSiys1DeG2Y/a2EClPPP+w9G8XMUQxNhdqRTom1v13ZA9QAQ34mSOMQVdxB2
Q8qvg8gV5W5t7bJFnCZcoiLasPB55Dff5vHvRsYsdxJdOE1d2E6F29z+2srS5Y3x
w2J/99PtKiTM/OxSCDse99lh3VIHl7e1vFW61q/CjPuJ3erW4Bctfa71+Ph0qMB2
6qn9SgkdKI1rA77aY8gHh9ONHo6a7l0/IWXFtwdusdgv/070oojRP5dU5DoY8V+A
/QIDAQAB
-----END PUBLIC KEY-----'
