# WPAdmin Button Agent Instructions

## Project Layout

- This directory is the GitHub repo for the WPAdmin Button plugin.
- Local release zip artifacts live outside the repo under `../releases/`.
- Keep release zip artifacts in versioned folders such as `../releases/v1.4.3/wpadmin-button.zip`.
- When plugin source or version metadata changes, refresh the relevant release zip so the local test artifact matches the source under review.

## Linear Workflow

Use this workflow when the user asks to work from Linear issues, notes, comments, projects, or status.

### Overview

Linear provides issue, project, documentation, and collaboration context for implementation work. Read Linear context first, then use repo and GitHub context to implement and publish the change.

### Prerequisites

- Linear tools must be connected and accessible via OAuth.
- Confirm access to the relevant Linear workspace, teams, and projects.

### Required Workflow

Follow these steps in order.

#### Step 0: Connect Linear

If Linear tools are unavailable, pause and ask the user to connect the Linear app:

1. Enable the bundled Linear app for this plugin or session.
2. Complete the Linear auth flow if Codex prompts for it.
3. Restart Codex or the current session if the tools still do not appear.

After the app is connected, finish the answer and tell the user to retry so the workflow can continue with Step 1.

#### Step 1: Confirm Goal

Clarify the user's goal and scope, such as issue triage, sprint planning, documentation audit, workload balance, or implementation. Confirm team, project, priority, labels, cycle, and due dates when needed.

#### Step 2: Select Tools

Select the appropriate workflow and identify the Linear tools needed. Confirm required identifiers such as issue ID, project ID, or team key before calling tools.

#### Step 3: Execute In Batches

Execute Linear tool calls in logical batches:

- Read first with list, get, search, and comment tools to build context.
- Create or update next with all required fields.
- For bulk operations, explain the grouping logic before applying changes.
- For implementation work tied to one Linear issue, use an existing open PR for that issue when one exists.
- Open a new PR when none exists or the previous PR for the issue was already merged.
- For WPAdmin Button changes that affect a test or release build, refresh the release zip under the matching version folder in `../releases/`.

#### Step 4: Summarize

Summarize results, call out remaining gaps or blockers, and propose next actions such as issue updates, labels, assignments, PR work, release artifacts, or follow-up comments.

### Available Linear Tools

Issue management:

- `list_issues`
- `get_issue`
- `create_issue`
- `update_issue`
- `list_my_issues`
- `list_issue_statuses`
- `list_issue_labels`
- `create_issue_label`

Project and team:

- `list_projects`
- `get_project`
- `create_project`
- `update_project`
- `list_teams`
- `get_team`
- `list_users`

Documentation and collaboration:

- `list_documents`
- `get_document`
- `search_documentation`
- `list_comments`
- `create_comment`
- `list_cycles`

### Practical Workflows

- Sprint Planning: Review open issues for a target team, pick top items by priority, and create a new cycle.
- Bug Triage: List critical or high-priority bugs, rank by user impact, and move the top items to In Progress.
- Documentation Audit: Search documentation, then open documentation issues for gaps or outdated sections.
- Team Workload Balance: Group active issues by assignee, flag overloaded contributors, and suggest or apply redistributions.
- Release Planning: Create a project with milestones and generate issues for release work.
- Cross-Project Dependencies: Find blocked issues, identify blockers, and create linked issues if needed.
- Automated Status Updates: Find stale issues and add status comments based on current state and blockers.
- Smart Labeling: Analyze unlabeled issues, suggest or apply labels, and create missing label categories.
- Sprint Retrospectives: Report on a completed cycle, note completed versus pushed work, and open discussion issues for patterns.

### Tips

- Batch related operations.
- Use natural Linear searches when appropriate.
- Reference prior issues and comments when they explain current work.
- Break large updates into smaller batches to avoid rate limits.

### Troubleshooting

- Authentication: Refresh OAuth access and verify Linear workspace permissions.
- Tool calls: Confirm identifiers and split complex requests if needed.
- Missing data: Check archived projects, team selection, and stale access tokens.
- Performance: Use specific filters and reuse known context when possible.
