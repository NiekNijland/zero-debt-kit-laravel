# Process: how every agent works on the cleanup

Mandatory for the lead, in full. A builder works from `08-builder-rules.md` and its brief alone; a reviewer or an auditor from `09-reviewer-brief.md`. If files disagree: this file wins for process, `08-builder-rules.md` for what a builder does, the project's `zero-debt-plan.md` for scope and order, and `01-principles.md` ("Who decides what") for everything between the kit, the repository and the owner.

## 0. Roles and files

| Role | Does | Never |
| --- | --- | --- |
| Lead | picks the next theme, briefs builders, commits each builder's finished theme from its commit plan, merges the builder branches into the integration branch, runs installs, pushes once per round, opens PRs, handles comments and conflicts, adds guards and rows in steps 4 to 9, keeps the hand-off files current | has two move branches open at once; force pushes |
| Builder | builds one theme in its own worktree, self-checks, writes its part of the PR description, reports with a commit plan | commits, pushes, opens, merges, approves or comments on a PR; touches files outside its theme. The one exception: a builder whose brief says it owns a separate PR's branch commits on that branch, and still never pushes |
| Reviewer (agent) | reads the integration branch cold with the rubric, breaks a sample of the changed tests, reports findings | edits the branch, pushes, comments, approves |
| Auditor | answers "what does no counter show?" at the start, at the end of each step and at the end (`06-lock-and-audit.md` E2) | builds; sees the builders' conclusions |

The four hand-off files, in every project of every size, in the location chosen in step 1 and outside the repository until the plan is archived (`06-lock-and-audit.md` E4):

| File | Content | Updated by |
| --- | --- | --- |
| `backlog.md` | every item with status and step, the PR table, decision lists, open loops, update lines | lead, after every merge |
| `zero-debt-plan.md` | dashboard, the catalog mapping and decisions, definition of clean, the ten steps for this project with their exit artifacts, the PRs of the current step, the audit log | lead, at the end of every step and when scope changes |
| `project-profile.md` | stack, CI, review and merge rules, hazards, verification policy | lead |
| `CONTINUE.md` | state, next steps, what the owner must do | lead, at the end of every session |

Each builder also gets `<theme>/brief.md` from the lead.

**A session that cannot start subagents.** The roles stay apart. The lead writes the brief (for a builder), or the reviewer or auditor brief of `09-reviewer-brief.md` with the branch name, to a file in the hand-off folder, and each role runs as a fresh session that is started from that file and has none of the lead's conversation. A lead never reviews or audits its own work in its own context: an agent that built the change returns its own conclusion.

## 1. Start of every session

1. Read `CONTINUE.md`, this file, then the last update in the backlog. At the start of a step, re-read only that step's part of `05-program.md` D2 and the catalog tables it names; `CONTINUE.md` lists them.
2. Check reality: fetch; list the program's open PRs with CI state, approvals, open threads and conflicts (`git merge-tree --write-tree --name-only origin/<base> <branch> | grep ^CONFLICT`).
3. After a pause (the owner stopped the program between steps, or the main branch has moved on since the last update): re-run the dashboard on the tip of the main branch, correct the plan's numbers and rewrite `CONTINUE.md` before any change. The lists held while nobody looked; the notes did not.

## 2. Lifecycle of a change

1. **Pick** the next theme of the current step. A new idea is a backlog row first.
2. **Brief** each builder with `templates/pr-description-and-brief.md`. A brief without an explicit file list, its own scratch folder, the ref to branch from and the kit's path is incomplete. The lead pastes into the brief the kit sections its theme needs (the template says which), so the builder opens no other kit file. Before the first brief, the lead copies `08-builder-rules.md` into the hand-off folder with the project's ticket filled in (the only value to fill in; every other pair of angle brackets in it is a format); every brief points at that copy.
3. **Build** in a worktree, following `08-builder-rules.md`. A builder does not commit and does not push; it reports each finished part with a commit plan (which files in which commit, with the messages), and the lead commits it at once.
4. **Integrate**: the lead merges the builder branches into the integration branch.
5. **Self-check on the integration branch**: the one local command, `bash scripts/checks.sh`, which is the file the checks step runs (`04-guards.md` C4). Its ratchet table shows every row +0 or lower, or "new row". Then a `git grep` over the whole repository, not a list of folders, proving no old name or old spelling is left (docs and guard lists are tracked files too); a dead-reference check for everything deleted or renamed; the smoke suite.
6. **Cold review** by a reviewer agent on the local integration branch, before the push. Findings are fixed before the push. A "no blockers" is not proof: for every claim the review marks as confirmed, the lead reads the method behind it and what that method cannot see (`09-reviewer-brief.md`, "Say how you know").
7. **Push once and open the PR** with the description of section 3. Reviewers are added per the project profile.
8. **Colleague review**: every finding is fixed in one push with the description updated, or answered as a misreading (section 5).
9. **Merge** by the project's merge rule; the lead merges only where the owner said so in step 1. First diff the PR against the current base: an earlier PR may have removed the debt it targets, and then it is closed, not merged. Never merge a hotfix or release-branch PR as part of the program.
10. **After the merge**: update the hand-off files (section 4). Read the result of the deploy pipeline itself, not only the PR checks: deploys can fail while every PR is green. For a PR that needs a deploy watch: when it is deployed, check what the profile lists (error tracker, queue, failed jobs) and record the result. The smoke suite never runs against production.
11. **End of the step**: `05-program.md` D4.

What every change carries:

- It empties part of a counted list, or adds a guard with its counted list. It never adds an entry by hand to an existing list.
- A new or changed pattern has its row in the one-pattern table and its guard in the same PR. In steps 4 to 9 a builder's PR only shrinks lists; a guard for a rule that a step finds is added by the lead (`04-guards.md` C7). The PR that empties a list deletes it with its row (`06-lock-and-audit.md` E1).
- A change to a script in `scripts/` or to a pipeline file is the lead's alone and carries the script tag (`04-guards.md` C2): the tag goes in the commit that touches the file, and the local run of `bash scripts/checks.sh` prints it when it is missing, so run it before the push.

## 3. The PR description

One template, `templates/pr-description-and-brief.md`: opening line, Benefit, What this does, Verify, Hand-written parts, Behavior that changes on purpose, Hazards, Counters. Plain language. The template says what each section holds; nothing is repeated here.

Move and rename themes also ship the map file (`05-program.md` D9).

**When the owner is the only human reviewer**, the owner reads three sections of every PR: Benefit, Behavior that changes on purpose, and Hazards. Everything else (the diff, the proof of the mechanical part, the counters) is checked by the cold reviewer and by CI, and the lead says so in the PR. So those three sections are written for the owner: complete, in plain words, with "None" written out.

## 4. Keeping the hand-off current

After every merge, and at least once per session:

1. `backlog.md`: the item's mark, the PR table, new hazards and open loops, one line under "Updates".
2. `zero-debt-plan.md`: at the end of a step, re-measure with the dashboard, update the numbers and paste the exit artifact in the step table.
3. `CONTINUE.md`: state and next steps.
4. Never store secrets, tokens, env contents, production or customer data.

## 5. Comments and approvals

- **Every comment gets an answer and every thread is closed.** Fix it (one push) or explain why it is a misreading, reply under the thread, resolve it. Before telling the owner a PR is ready, scan it for unresolved threads and open tasks: both zero. Scan again after every push.
- **An approved PR is not changed for findings.** The change goes in a follow-up PR, opened after the approved one merges; reply with what the follow-up will carry and resolve the thread.
- **The two exceptions**: a conflict with the base branch, or a pipeline that is red because of the code, on an approved PR is fixed and pushed at once. Red for infrastructure reasons is not a reason to push (section 11).
- Where a push resets approvals (on many hosts a merge of the base does too), the PR is final before it is offered for review. Collect every thread first, answer all in one push, and merge the base before asking for the approval.
- **A green pipeline and an empty approval count say nothing about open threads.** Before a PR is called ready or approvals are asked, list every comment of the PR through the API: it is ready only when each thread has a reply and is resolved. Recheck after every push and while waiting, because a reviewer may comment in between.
- **Read thread state per comment.** On Bitbucket the comment list shows no resolution; only `GET .../comments/{id}` carries it (or call the resolve endpoint and read the "already resolved" refusal). A check built on the list reports every thread as open.
- **Before pushing to a branch whose PR has an approval, check the PR state**: an owner who merges between two pushes loses the last commits. After a merge, diff the merged branch's tip against the second parent of the merge commit and bring any later commit forward in a follow-up.
- **Fix forward; never revert in git.** A merged program PR that turns out wrong is corrected by a new PR. A revert would raise the rows the PR lowered, and no tag lets a row rise from 0 (`04-guards.md` C2), so a revert PR cannot pass. A production emergency is handled outside git: the person named in step 1 redeploys the previous release, and the fix then goes forward as a PR.

## 6. Conflicts

When the base moves under an open program PR, the lead:

1. fetches the base in the same command as the conflict check;
2. for a scripted theme, takes the base's version, re-runs the codemod on it and regenerates its list, instead of resolving by hand. Codemod branches are never stacked on each other for the same reason;
3. otherwise merges the base, takes the base's version of files the PR only moved or reformatted, and re-applies the PR's intent;
4. greps the merged tree for every class the PR deleted or renamed (taking the base's side can re-add a reference to a deleted class);
5. runs the self-check and the ratchets, with the whole guard folders and not only the tests the PR touched (new rules arrive with the base), pushes once, and notes one line per resolved file in the backlog.

## 7. Parallel builders

- Each builder gets its own worktree, on a local branch the lead creates from the ref the brief names (the tip of the integration branch), its own scratch folder, and a disjoint file set named in its brief. Every brief names the files and the scratch folder explicitly, also when builders have a worktree each.
- Size a brief by the number of classes or files, not by theme, and split by domain up front: a builder runs out of context after a few dozen classes. Use fewer, larger builders (three or four): seven that all hit a usage limit together finish less. After a limit, check each worktree's `git status` and resume by message, not with new builders.
- A brief of twelve items run by one builder keeps CI red for hours: split it into builders on disjoint files that run in parallel (`05-program.md` D5 allows it).
- Every builder that runs a test suite gets its own test database server; two suites never share a database, and parallel agents never share one server that they can exhaust.
- A generated baseline is a shared file: the lead regenerates it once, after merging the builder branches, and only to drop fixed entries. New findings in new code are fixed, never baselined.
- One heavy analysis at a time across all builders (whole-tree analyser, dead-code run), with a worker limit: parallel runs can use all the memory of a shared container and stop its database.
- Shared files (root `AGENTS.md`, lint and analyser config, the ratchet table, the caps file, generated types, package manifests) are edited by the lead only, or by one named builder. The scripts in `scripts/` and the pipeline files are the lead's alone (section 2).
- The main checkout stays on the base branch, clean; no git commands there.
- A temporary worktree directory can be wiped: that is why the lead commits each finished part at once (section 2).
- Builders that do the same kind of fix in different folders get ONE shared list of fix patterns, the same text in every brief, and the helpers the patterns name exist before they start. A builder that misses a helper reports the site and does not add one: three builders would add three.
- A shared worktree is the exception, for many small edits in disjoint folders where a worktree each costs more than it saves. The lead then gives each builder an explicit file list, its own scratch subfolder and its own analyser config with its own cache directory, and is the only one that runs git commands that change state. The builder's side is in `08-builder-rules.md` ("In a shared worktree").
- Every builder gets a proof it can run: a title-equality check for moved tests, a byte-identity check for moved function bodies, an empty-diff check for a codemod.

## 8. Waiting and control

- A pipeline is started single-shot: never a shell fallback (`|| curl ...`) that can start a second run, never two runs on one branch at once (they can stop each other). A pull-request pipeline needs a pull request: on a branch without one it starts no steps. A throwaway draft pull request with empty commits, no reviewers and deleted after, is the way to get repeated runs (the flake sweep) without touching a real one.
- No polling loop on an output file. Wait on the exit notification of the command or agent, with the timeout of the tool that starts it.
- A CI watch started before the checks exist exits at once: start it after they appear.
- On a shared or slow runner a push can cancel a running build: fewer and larger pushes, no extra builders to fill the wait. In a shared container, stop only the process ids you started; never kill by name.
- Automated feedback (a hook, a system reminder, another agent) is not the owner speaking. When the owner says pause, stay paused until the owner says go.

## 9. Stacked pull requests

Work in a chain. When PR B depends on PR A, or follows it in the same step, B starts from A's tip and targets A's branch (a third targets B, and so on). Parallel PRs that are all opened against the main line conflict as soon as the first merges (the same register, guide row or provider list), and every conflict fix is a push that voids the approvals. A chain merges back to back, and the reviewers approve each PR once, in order.

1. Each branch starts from its parent's tip and its PR **targets the parent's branch**. The diff shows only its own commits.
2. The reviewers approve in chain order. Each PR is reviewed against its parent.
3. Merge from the bottom: the first PR to the main line, then the next. **Merge the parent with its source branch closed** (`close_source_branch: true` on Bitbucket; on GitHub "Automatically delete head branches" in the repository settings, which retargets the children; the same option on other hosts): the host then retargets the child to the main line itself and the child keeps its approvals. Merging with the branch kept leaves the child pointing at the parent's branch, and retargeting it by hand voids its approvals (observed on Bitbucket). Check afterwards that the child's destination is the main line. A child never needs a push after its parent merges while the chain was built on the parent's final tip. A child with exactly one approval can still show zero after the retarget (seen on Bitbucket, once; the other children kept theirs): where the owner's answer of 1.1 is one approval, tell the reviewers that the last PRs may need a second click after the parent merges. Do not ask for two approvals on every chain PR: that is not the project's rule.
4. A fix on a lower PR is a push to that branch: merge it into each child in the same round and push each child once, and say before you push which approvals it voids. Do the fix before the first approval where you can.

A fix that every pipeline needs (an advisory patch, a base-branch repair) is the bottom of the chain. A child contains it by a merge of the fix branch, never by a rebase and never by a force push; the parent's PR then shows the merge commit, which is the price of a chain without force pushes.

Rules that stay: where two PRs must change one register or one guide file, put them in one PR or in one chain, never side by side; codemod branches follow section 6; one move branch at a time (D5).

**The merge button merges into the destination the PR was opened with.** Before merging a chained PR, read its destination branch. After every merge confirm `git merge-base --is-ancestor <head> origin/<base>` (the branch the team deploys from): a commit that landed on a chain parent never reached the main line. Never merge a parent to the main line while its child still targets it unless you have checked the host retargets (a closed child is the cost). Retargeting a PR can start a pipeline run on some hosts: count it as a push. The ratchets compare with the base the run was given, so a green result computed against the parent branch is stale after a retarget: confirm that a run against the new base exists, and start one (a push, or the host's re-run of the pull-request pipeline) where it does not. GitHub fires the `edited` event with `changes.base` for a retarget, and the skeleton lists that type (`04-guards.md` C4); that the automatic retarget after "Automatically delete head branches" fires it is not proven here, so test one chain once before the program relies on it. Between the retarget and the first event of that run the merge box shows the old result of the head commit; the repository setting "Require branches to be up to date before merging" is what makes that stale result unmergeable whether or not `edited` fires, because a retargeted child that lacks the parent's squash or merge commit is out of date until it is updated (reasoned, not run).

## 10. A check that fails every open pull request

Some checks, once merged, turn every other open PR red at its next merge of the base, although nothing in that PR changed: a formatter check on the whole tree (every branch holds files in the old format), a stricter commit-message rule (it reads commits that already exist on the branch), a lint rule or compiler flag switched on, a new guard without a list.

- Land it when few PRs are open, never just before a release, and not inside a freeze.
- Release PRs and automatic merge-backs of the release flow conflict with the program: plan the merge between releases, and merge the base into the integration branch right before asking for the approval.
- The person named in step 1 announces it a day before, in the place named there, with the one command that makes a branch pass (for a formatter: merge the base, then run the formatter on the whole tree).
- A commit-message check reads only the commits of the PR, so commits already on the main line never fail it; a commit in the old form on an open branch does. Such a branch becomes a fresh branch with its commits written in the new form; nothing is force pushed. A commit with more than one parent (a merge of the base into the branch) is never checked; a single-parent commit titled `Merge branch ...` or `Merge pull request ...` is no longer accepted by `ci-commit-messages.sh`, because that title would hide work: write the real form, or squash it away on a fresh branch. A title a tool produced is accepted by its exact text for as long as open branches hold it (the `GENERATED` pattern). A bot that opens PRs (dependency updates) is configured to the form before the check lands (its commit-message prefix setting).
- The pull request that installs the tools (step 2) is such a check. On a host that checks out the branch as it is (GitLab, unless merged results pipelines are on), a branch cut before that PR has no `scripts/checks.sh`, and its checks job fails on the missing file until the branch merges the base. On a host that merges the base first (Bitbucket, GitHub), the same branch is checked as that merge at once.
- After it lands, list the open PRs and their state once, and tell their authors.

## 11. Reading a red step

Before debugging code, find out what kind of red it is:

- **No test output, red in the first seconds**: usually a merge conflict, because the pipeline merges the base first. Look for `CONFLICT` in the log. On GitHub a pull request that conflicts with its base has no merge ref, so its pull-request workflows do not start at all: no red step, no log, and the required checks stay "Expected" until the base is merged into the branch (by the host's documentation; not run here).
- **A run that ends as cancelled**: on GitHub the `concurrency` group cancels the older run of a pull request when a newer one starts. That is not a failure; read the newest run.
- **A log of about ten lines that ends before the script starts**: the runner, not the code. An image that could not be pulled or a service container that did not start. The step's error object on the host's API says so; the log does not. This is red for infrastructure reasons: re-run the step once. A second failure goes to the owner of the runner (named in the profile), with the error object. The PR is not changed and nothing is pushed "to see if it helps".
- **A test step that ends without a report and without test names**: a fatal error in a worker of the parallel runner, not a failed assertion. Find the first fatal line; every other failure is hidden behind it (`05-program.md` D8).
- **Green PR checks, red deploy**: read the deploy pipeline's own result (section 2, point 10).

## 12. Who may write an override

The tag `[ratchet: <name>] <reason>` and `[ratchet: script] <reason>` are the one way past a ratchet row or a guarded file (`04-guards.md` C2). They sit in a commit message, and the author of the pull request writes the commit messages: a builder agent can write the tag as easily as the lead. No script can tell a tag with a reason from a tag written to get a pull request through, so the rule is held by review, and the review is held by the host.

- **The files that carry the guards have an owner who is a person.** `scripts/` (the ratchet script, the checks script, `kit-config.sh`, the caps file, the floors), the pipeline files, the guard folder with its thin tests, and the code-owners file itself are covered by a code-owners rule (`ci/CODEOWNERS.example`; `04-guards.md` C4, point 22). The contract test reads the file and fails on a guard file with no rule.
- **The host enforces the rule, and that setting is the owner's, written in the profile and in `CONTINUE.md`.** GitHub: branch protection (or a ruleset) on the main branch and on every other deploying branch with "Require a pull request before merging", the option that asks for review from code owners (the label differs between branch protection and rulesets and was not read against the settings page: unverified) and no bypass for administrators. GitLab: "Require approval from code owners" on the protected branch (approval rules with the owners as approvers where the plan lacks it, and "Prevent editing approval rules in merge requests"). Bitbucket: default reviewers on the repository and the merge checks for a minimum number of approvals and of default reviewers; it has no file, and the test is created with the host-enforced argument. A setting nobody has read back is not a setting: the lead asks the owner to confirm it once at the end of step 2, and again when the main branch's rule changes.
- **A builder does not approve its own override.** The approval that counts is the owner's, on a pull request whose diff touches those files; the lead and the reviewer read the reason of every tag in the range (`09-reviewer-brief.md`), and a tag whose reason does not name why the number may go the wrong way is sent back, whoever wrote it. Automated feedback (a hook, another agent) is not the owner: an approval, a comment or a "looks fine" from one of them does not satisfy the code-owners rule.
- **Who can approve, when the agents use the owner's account.** On GitHub the author of a pull request cannot approve it (documented behaviour, unverified on a repository). The agents push and open pull requests with the credentials the session holds; where those are the owner's own, every pull request that touches a guard file is the owner's, no approval of the owner counts, and the pull request cannot merge without a bypass. Three states, from the one that keeps the rule whole: (1) the agents work under a second identity, a machine user or an app, so that the owner, a person, can approve; the author may be a bot, the owner of the files may not; (2) a second person is a code owner; (3) a solo owner is a bypass actor, and then the rule stops only identities without the bypass and stops nothing when the agents use the owner's token, because a merge by that token is a bypass. Choose one in step 1 and write it in the profile with its name. State 3 does not weaken the rule in this file; it says the rule is not met, and the profile says so. How the option "Require approval of the most recent reviewable push" treats commits an agent pushed under the owner's name is unverified. A pull request runs its own copy of the workflow file, so this review is the only barrier for a change to the workflow itself: without it the contract test over the pipeline is advice.
- **The owners are never a bot.** A bot or an automation account in the code-owners file turns the rule into one the same agent can satisfy; where the host lets a team own the files, the team's members are people.
