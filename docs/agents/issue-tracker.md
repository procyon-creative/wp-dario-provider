# Issue tracker: Jira

Issues and specs for this repo live in Jira, not GitHub Issues. **`docs/jira.md` is the source of truth** for the project, MCP server, ticket requirements, branch naming, and how tickets move. Follow it for every Jira operation; this file only maps the skills' generic operations onto it.

## Conventions

Use the `mcp__procyon_atlassian__*` tools of the MCP server `docs/jira.md` names.

- **Create a ticket**: `/jira-ticket`, or `createJiraIssue`.
- **Read a ticket**: `getJiraIssue`, including comments.
- **List tickets**: `searchJiraIssuesUsingJql`, filtered by label and status.
- **Comment on a ticket**: `addCommentToJiraIssue`.
- **Apply / remove labels**: `editJiraIssue` on the `labels` field.
- **Close**: comment the reason; status changes follow `docs/jira.md`.

## Pull requests as a triage surface

**PRs as a request surface: no.**

## When a skill says "publish to the issue tracker"

Create a ticket as `docs/jira.md` describes.

## When a skill says "fetch the relevant ticket"

Fetch the ticket by key with `getJiraIssue`. The key comes from the branch name.

## Wayfinding operations

- **Map**: an Epic.
- **Child ticket**: a ticket whose parent is the map Epic, labeled `wayfinder:<type>` (`research`/`prototype`/`grilling`/`task`).
- **Blocking**: a Jira `Blocks` issue link. A ticket is unblocked when every blocker is Done.
- **Frontier query**: the Epic's children that are not Done, unassigned, and have no open blocker; first in rank order wins.
- **Claim**: assign the ticket to yourself.
- **Resolve**: comment the answer and append a pointer to the map's Decisions-so-far. Ticket status changes follow `docs/jira.md`.
