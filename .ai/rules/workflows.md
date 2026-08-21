---
paths:
  - '.github/workflows/**'
---

# Workflows

## main is gated twice, and both halves have to exist
The deploy job's `needs: ci` gate is only half the story. The other half is a repo ruleset named `main` (id 21164250) requiring a pull request plus the `ci` status check. The comment above the deploy job says to "keep branch protection as well" - that was aspirational until 2026-08-21, when it turned out main had no protection or rulesets at all and `gh pr merge --auto` merged PR #1 while CI was still running. The deploy stayed gated; the merge did not.

If you rename the `ci` job, the required check context changes with it and the ruleset silently stops matching, which leaves PRs waiting on a check that will never report. Update the ruleset in the same change.

Approvals are set to 0 on purpose - a two-person team cannot approve its own PRs. `require_extra_approval_for_unattributed_changes` is off on purpose too: GitHub defaults it on, and it demands an approval nobody can give for commits carrying a Co-Authored-By trailer. Repository admin can bypass, which is the deliberate escape hatch.
