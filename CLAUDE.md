<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/vue3 (INERTIA_VUE) - v2
- vue (VUE) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

# Git Commits

- Write every commit message in English, regardless of the language used in the conversation.
- Follow the Conventional Commits spec: `<type>(<optional scope>): <description>`.
  - Allowed types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`.
  - Use a scope when it clarifies the affected area, e.g. `feat(cashier): add split payment`.
  - Keep the description in the imperative mood and lowercase: "add", not "added" or "Add".
  - For breaking changes, append `!` after the type/scope (e.g. `feat(api)!: ...`) or add a `BREAKING CHANGE:` footer.
- Make atomic commits: each commit is one logical, self-contained change that builds and passes tests on its own.
  - Do not bundle unrelated changes into a single commit; split them into separate commits.
  - Stage only the files relevant to that change (avoid a blanket `git add .` when the working tree has unrelated edits).
  - **A class and its callers go in the same commit, or the class goes first — never the other way round.** This is the rule that `[BL-072]` was opened for; read the warning below before assuming it is theoretical.
  - Verify it before committing: `composer run check:boot`. It boots the framework and then scans every top-level import in `app/`, `database/`, `routes/`, and `config/` for a class that cannot be loaded — the exact shape of `[BL-072]`. Note that `php artisan route:list` alone does **not** catch this (tried, and it exits 0): a `use` statement is a compile-time alias and never reaches the autoloader until the class is actually used.
- Use a single author only. Do not add a `Co-Authored-By` trailer (or any other co-author) to commit messages.

## History warning — commits `2ffd393`..`329f592` cannot boot (`[BL-072]`)

Six consecutive commits reference `PaymentAttempt`, `AdaptiveEligibility`, and `PaymentGatewayManager` before those files existed; all three landed together in `342082c`. Because `HandleInertiaRequests` is on every request, **no Inertia page renders anywhere in that range** — verified, not inferred (`c0add23` + `tests/Feature/Subscription` = 25 failures, all `Class "App\Models\PaymentAttempt" not found`).

`HEAD` is healthy. The trap is only sprung by someone walking the history:

- **Do not `git bisect` across the range.** Every commit in it fails for a reason unrelated to whatever you are hunting, so bisect will confidently name the wrong commit. Use `git bisect skip 2ffd393..329f592`, or start from `342082c..HEAD`.
- **Do not `git revert 342082c`.** It would take those three classes away from the commits below it and **kill `HEAD`**, not just remove the payment gateway. To drop the gateway, write a new commit that removes its callers first.

Rewriting the range was considered and declined by the owner in 2026-08-08: reconstructing the intermediate states means guessing the intent of someone else's hunks, and history rewritten from guesses is not more trustworthy history. The range stays broken on purpose — this warning is the fix.

# Changelog & Backlog

- The project changelog lives at `docs/CHANGELOG.md`. Record out-of-phase changes and architecture decisions there.
- Write a changelog entry only when the change is actually applied (implemented in the codebase), not while it is still being planned or proposed. Add the entry as part of the same work that lands the change.
- Every new changelog entry must also add **one row at the top of the `## Indeks Entri` table** in `docs/CHANGELOG.md`. An entry with no index row will not be found.
- Open issues and technical debt live in `docs/BACKLOG.md`. When an entry is finished, move its full text to `docs/BACKLOG-ARCHIVE.md` and leave one row in the "Riwayat Selesai (Arsip)" table in `docs/BACKLOG.md`.

## Reading these files (do not read them in full)

`docs/CHANGELOG.md` is append-only and already ~1,475 lines / ~37k tokens. Reading it whole is never the right move — it costs more context than the rest of a typical task combined. Use this order instead:

1. **Read the index, not the file.** `Read docs/CHANGELOG.md offset=58 limit=78` returns the whole `## Indeks Entri` table (~2k tokens) — date, type, area, and exact title of every entry.
2. **Jump to the one entry you need.** `Grep` the title from the index to get its line number, then `Read` with `offset`/`limit` around it. Entries are 15–35 lines each.
3. **Fallback that never goes stale.** If the index looks incomplete, list headings directly: `Grep` pattern `^### ` on the file with `-n`. That derives the table of contents from the file itself.

Same protocol for `docs/BACKLOG.md` (open issues only) and `docs/BACKLOG-ARCHIVE.md` (finished ones): grep `^### \[BL-` to locate an entry, then read only its range. Whether a `BL-xxx` is already done can be answered from the "Riwayat Selesai (Arsip)" table in `docs/BACKLOG.md` without opening the archive at all.

The same applies to the large phase documents under `docs/phases-1/` and `docs/phases-2/` — grep for the relevant section rather than reading the file end to end.

