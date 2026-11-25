# Guard: queued classes

A unique job declares `uniqueId()`, so the lock is per record and not per class.

| Rule id | The guide must say (the words the guard pins) | Instead |
| --- | --- | --- |
| unique-job-key | `uniqueId()` | declare public function uniqueId(): string and return the key of the record the job works on |

Review checks, and the guard does not: whether `uniqueId()` returns the right key (the row "Queued class" of the rubric).
