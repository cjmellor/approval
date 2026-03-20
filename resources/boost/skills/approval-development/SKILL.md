---
name: approval-development
description: Build and work with cjmellor/approval features, including approval workflows, custom states, time-based expirations, rollbacks, and event handling.
---

## When to use this skill

Use this skill when working with model approval workflows, intercepting create/update operations for review, managing approval states, setting up time-based expirations, or handling approval events using cjmellor/approval.

## Core Concepts

- **Models use `MustBeApproved` trait** — Intercepts `creating` and `updating` events, storing dirty data in the `approvals` table instead of persisting directly.
- **Approvals have states** — `Pending`, `Approved`, `Rejected` (via `ApprovalStatus` enum), plus custom states defined in config.
- **Approve to persist** — Data only reaches the model's table when explicitly approved.
- **Bypass with `withoutApproval()`** — Skip the approval workflow for admin actions, seeders, etc.
- **Expiration support** — Approvals can auto-reject, auto-postpone, or trigger custom logic after a deadline.

## Setup

Add the `MustBeApproved` trait to any model that needs approval:

```php
use Cjmellor\Approval\Concerns\MustBeApproved;

class Post extends Model
{
    use MustBeApproved;

    // Optional: only these attributes require approval, others persist immediately
    protected array $approvalAttributes = ['title', 'content'];
}
```

## Approving, Rejecting, and Postponing

```php
use Cjmellor\Approval\Models\Approval;

// Query by state
$pending = Approval::pending()->get();
$approved = Approval::approved()->get();
$rejected = Approval::rejected()->get();

// Change state
Approval::where('id', 1)->approve();       // Persists new_data to the model's table
Approval::where('id', 2)->reject();        // Marks as rejected
Approval::where('id', 3)->postpone();      // Resets to pending

// Approve without persisting data (state change only)
Approval::where('id', 1)->approve(persist: false);

// Conditional helpers
$approval->approveIf($user->isAdmin());
$approval->rejectUnless($hasPermission);
$approval->postponeIf($needsMoreInfo);
```

## Custom Approval States

Define custom states in `config/approval.php`:

```php
'states' => [
    'pending' => ['name' => 'Pending', 'default' => true],
    'approved' => ['name' => 'Approved'],
    'rejected' => ['name' => 'Rejected'],
    'in_review' => ['name' => 'In Review'],
    'needs_info' => ['name' => 'Needs Clarification'],
],
```

```php
// Set any configured state
$approval->setState('in_review');

// Get current state (returns custom state if set, otherwise standard state)
$state = $approval->getState(); // 'in_review'

// Query by any state
Approval::whereState('in_review')->get();
Approval::whereState('pending')->get(); // Only genuinely pending, excludes custom states
```

## Time-Based Expirations

```php
// Set expiration with automatic action
$approval->expiresIn(hours: 48)->thenReject();
$approval->expiresIn(days: 7)->thenPostpone();
$approval->expiresIn(minutes: 30)->thenCustom(); // Handle via ApprovalExpired event listener

// Specific datetime
$approval->expiresIn(datetime: now()->addWeek());

// Check expiration
$approval->isExpired(); // bool

// Query by expiration
Approval::expired()->get();
Approval::notExpired()->get();
Approval::hasExpiration()->get();
```

Process expired approvals via the scheduler:

```php
// In routes/console.php (Laravel 11+)
Schedule::command('approval:process-expired')->everyMinute();
```

## Rollbacks

```php
// Roll back an approved change (keeps Approved state by default)
$approval->rollback();

// Roll back and reset to Pending (requires re-approval)
$approval->rollback(bypass: false);

// Conditional rollback
$approval->rollback(condition: fn ($approval) => $approval->created_at->isToday());
```

## Requestor / Creator Tracking

```php
// Get who created the approval
$requestor = $approval->requestor;    // The authenticated user at creation time
$creator = $approval->creator;        // Same relationship, different name

// Filter by requestor
Approval::requestedBy($user)->get();

// Check if a specific user requested it
$approval->wasRequestedBy($user); // bool
```

## Foreign Keys

```php
// Default foreign key is 'user_id' — customize per model:
public function getApprovalForeignKeyName(): string
{
    return 'author_id';
}

// Include the foreign key when creating:
Post::create(['title' => 'Hello', 'user_id' => auth()->id()]);
```

## Events

All events extend `ApprovalEvent` with `Approval $approval` and `?Authenticatable $user`:

| Event | When |
|-------|------|
| `ApprovalCreated` | Approval request created |
| `ModelApproved` | Approval state set to approved |
| `ModelRejected` | Approval state set to rejected |
| `ModelSetPending` | Approval state set to pending (postponed) |
| `ModelRolledBack` | Approved change rolled back |
| `ApprovalExpired` | Expired approval processed by scheduler |

```php
use Cjmellor\Approval\Events\ModelApproved;

class SendApprovalNotification
{
    public function handle(ModelApproved $event): void
    {
        $approval = $event->approval;
        $approver = $event->user;

        // Send notification, log audit trail, etc.
    }
}
```

## Factory Support

Use the `MustBeApprovedFactory` trait in model factories to bypass approval in tests:

```php
use Cjmellor\Approval\Concerns\MustBeApprovedFactory;

class PostFactory extends Factory
{
    use MustBeApprovedFactory;

    // ...
}

// In tests:
Post::factory()->withoutApproval()->create();
```

## Bypassing Approval

```php
// Bypass for a single operation
$post->withoutApproval()->update(['title' => 'Admin Override']);

// Check if bypass is active
$post->isApprovalBypassed(); // bool
```

## How Data Flows

When a model with `MustBeApproved` is created or updated:

1. The trait intercepts the `creating`/`updating` Eloquent event
2. Dirty attributes are captured as `new_data`, original values as `original_data`
3. If `$approvalAttributes` is defined, only those attributes go through approval — the rest persist immediately
4. The foreign key (default `user_id`) is extracted and stored separately in the `foreign_key` column
5. An `Approval` record is created with `state = pending`
6. The original model operation is cancelled (returns `false` from the event)
7. When approved, `new_data` is applied to the model via `forceFill()` + `withoutApproval()->save()`

The `new_data` and `original_data` columns are cast to `AsArrayObject`, so you can access them as arrays:

```php
$approval->new_data['title'];       // The proposed new title
$approval->original_data['title'];  // The original title before the change
```

## Common Patterns

### Building an Approval Dashboard

```php
// Get all pending approvals with their related models
$approvals = Approval::pending()
    ->with('approvalable', 'requestor')
    ->latest()
    ->paginate(20);

// In a Blade view
@foreach ($approvals as $approval)
    <div>
        <p>{{ $approval->requestor?->name }} wants to change {{ class_basename($approval->approvalable_type) }}</p>
        <p>Changes: {{ json_encode($approval->new_data->toArray()) }}</p>
        <form method="POST" action="/approvals/{{ $approval->id }}/approve">
            @csrf
            <button type="submit">Approve</button>
        </form>
    </div>
@endforeach
```

### Approval Controller

```php
class ApprovalController extends Controller
{
    public function approve(Approval $approval)
    {
        $approval->approve();

        return back()->with('success', 'Changes approved and applied.');
    }

    public function reject(Approval $approval)
    {
        $approval->reject();

        return back()->with('success', 'Changes rejected.');
    }
}
```

### Duplicate Detection

The package prevents duplicate pending approvals for the same model with identical data. If a user submits the same change twice, only one approval record is created.

### JSON / Array Cast Attributes

Models with `json` or `array` casts work correctly. The approval system decodes JSON values when storing `new_data` and re-casts them when approving. Nested arrays and complex data structures are preserved.
