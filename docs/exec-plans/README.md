# Execution plans

Working directory for agent execution plans, as required by the agent-harness docs/ structure.

- `active/` — plans for work currently in progress (create the directory with the first plan)
- `completed/` — finished plans kept for traceability

A plan is a short markdown file: goal, ordered steps with verification commands, and completion criteria. Delete or move a plan to `completed/` when the work merges; do not let stale plans accumulate in `active/`.
