# Testing Best Practices

Follow [AGENTS.md](../../../../AGENTS.md), the existing [test bootstrap](../../../../tests/TestCase.php), and [PREP-001](../../../../docs/stabilization/tasks/PREP-001.md) for isolation and proportional verification. Use the project's PHPUnit tests and installed tooling; preserve existing assertions and report what was actually run.

## Choose Database Refresh Timing for the Fixture

`RefreshDatabase` runs database preparation when the test traits initialize, then uses transactions on configured connections. In the normal non-truncating flow, the process-wide migrated flag avoids repeating the initial migration.

`LazilyRefreshDatabase` defers that same preparation until the first query or transaction on a configured connection. It does not inspect whether the schema is up to date. Choose it only when deferred initialization fits the fixture. When setup redirects application/database paths after trait initialization, check when migrations will run; use the existing eager `RefreshDatabase` pattern where deferred migration would use an empty fixture directory.

## Assert the Behavior Being Protected

Use `assertModelExists()` to prove existence. Use `assertDatabaseHas()` or refreshed model values to prove particular attributes, ownership, or unchanged state; existence alone cannot establish those contracts. Include refusals, failure effects, and recovery where relevant to the change.

## Use Factory States and Sequences

Use existing named states such as `User::factory()->unverified()` and sequences for clear fixtures. Use explicit attributes when no suitable state exists. `recycle()` can share intended relationship instances instead of accidentally creating unrelated records.

## Fake External Effects Deliberately

Use the project's mail, notification, queue, storage, and HTTP isolation patterns. Configure the required fakes before the action that may produce those effects. If factories need model events for UUIDs or relationships, create them before a broad `Event::fake()`, or fake only the events under test.

Use `Exceptions::fake()` when asserting exception reporting. Preserve normal exception handling when testing HTTP error contracts; use `withoutExceptionHandling()` only when an unhandled exception is the intended diagnostic or assertion.
