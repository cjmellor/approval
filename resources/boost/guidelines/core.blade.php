## Approval Package

- This application uses `cjmellor/approval` to intercept model create/update operations and route changes through an approval workflow before persisting.
- Models using the `MustBeApproved` trait will have their dirty data stored in an `approvals` table instead of being saved directly. The data must be explicitly approved, rejected, or postponed.
- Use `$model->withoutApproval()` to bypass the approval workflow when needed (e.g. seeders, admin actions).
- Use the `search-docs` tool if available to look up Laravel-specific patterns for working with approval workflows.

### Key Classes

- `Cjmellor\Approval\Models\Approval` — The approval record model. Query with scopes like `approved()`, `pending()`, `rejected()`, `expired()`.
- `Cjmellor\Approval\Concerns\MustBeApproved` — Trait to add to models that require approval.
- `Cjmellor\Approval\Enums\ApprovalStatus` — Enum for standard states: `Pending`, `Approved`, `Rejected`.
- `Cjmellor\Approval\Enums\ExpirationAction` — Enum for expiration actions: `Reject`, `Postpone`, `Custom`.

### Approval Workflow

<code-snippet name="Basic Approval Workflow" lang="php">
use Cjmellor\Approval\Concerns\MustBeApproved;

class Post extends Model
{
    use MustBeApproved;

    // Optionally limit which attributes require approval
    protected array $approvalAttributes = ['title', 'content'];
}

// Creating/updating sends data to approvals table
Post::create(['title' => 'My Post', 'content' => 'Hello']);

// Approve, reject, or postpone
$approval = Approval::pending()->first();
$approval->approve();   // Persists data to the posts table
$approval->reject();    // Marks as rejected
$approval->postpone();  // Resets to pending
</code-snippet>

### Time-Based Approvals

<code-snippet name="Expiration Example" lang="php">
$approval->expiresIn(hours: 48)->thenReject();
$approval->expiresIn(days: 7)->thenPostpone();
$approval->expiresIn(hours: 24)->thenCustom(); // Listen for ApprovalExpired event
</code-snippet>

### Events

All events extend `ApprovalEvent` with typed `Approval $approval` and `?Authenticatable $user` properties:

- `ApprovalCreated` — Fired when an approval request is created
- `ModelApproved` — Fired when an approval is approved
- `ModelRejected` — Fired when an approval is rejected
- `ModelSetPending` — Fired when an approval is postponed
- `ModelRolledBack` — Fired when an approved change is rolled back
- `ApprovalExpired` — Fired when an expired approval is processed
