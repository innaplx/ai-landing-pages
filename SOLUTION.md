# AI Landing Page Generator: Solution Document

## 1. How I understood the request
Marketing wants to describe a landing page in plain language, from their own AI client, and get a page on the WordPress site that:
- looks like the rest of the site (whatever theme it runs),
- can be edited like any other page in the block editor,
- follows the rules of its page type, while sections can be optional or repeated,
- can grow to new page types without a rebuild.

What I read as the core problem: the AI is creative and unpredictable, and the site has to stay consistent anyway. So the AI decides **content and composition**, and the plugin decides **structure and design**.

## 2. Open questions and my assumptions
| Question I'd ask stakeholders | Assumption I made |
|---|---|
| Who approves a page before it goes live? | Pages are always created as **drafts**. A person reviews and publishes. |
| Which page types exist today, and what sections do they use? | One example type, `webinar`, with Hero, Speakers (repeatable), Agenda, FAQ (repeatable, optional) and CTA sections. |
| Who adds new page types? | A developer, through configuration (no new code per type). An admin UI for this is a later step. |
| Should the AI update existing pages? | No. It only creates pages. Editing happens in the block editor. |
| Images? | Placeholders the team swaps in the editor. Media library support is a next step. |
| Is the live theme a block theme or a classic theme? | Block theme with `theme.json`. Sections use core blocks only, which also render in classic themes, but presets would need a fallback there. |
| Where do forms and CTAs connect (CRM, Marketo)? | Out of scope. CTAs are plain links. |
| SEO, multiple languages / RTL, analytics? | Title and slug only, single language. |
| Who can use it, and with what rights? | Each person connects with their own WordPress user (Application Password), editor role or lower. |

## 3. Proposed approach
### 3.1 Integration: an MCP server inside the plugin
- MCP is the open standard most AI clients support, so it works from Claude or any other client, with no separate app.
- The plugin registers its tools as abilities (WordPress Abilities API, in core since 6.9), marked public for MCP, and the official WordPress MCP Adapter serves them over HTTP. I chose the official standard over a hand-written JSON-RPC endpoint: less protocol and security code to own, and it follows where WordPress is going.
- Auth uses each person's own WordPress account, so normal roles and capabilities apply and every page is attributed to its author.

**Connecting, prototype vs production**
| | Prototype | Production |
|---|---|---|
| How | Claude Code / Desktop with an Application Password (Desktop via `@automattic/mcp-wordpress-remote`) | Custom connector in claude.ai, added once by the org Owner; each marketer clicks Connect and signs in with their WordPress user (SSO if possible) |
| Auth | Application Password per user | OAuth 2.1. Core WordPress has no OAuth server, so I'd use an existing plugin (e.g. Enable Abilities for MCP) rather than write one |
| Why | Works in minutes, enough to prove the flow | No config files or passwords for non-technical users; access is revoked in one place |

Rejected: one shared service account (no attribution, can't revoke per person), a secret-token URL (the URL becomes the password), a chat inside WordPress (a standalone AI app, which the brief rules out), and the WordPress.com/Jetpack connector (requires Jetpack and WordPress.com, so the plugin is no longer the deliverable).

### 3.2 Tools exposed to the AI
| Tool | Purpose |
|---|---|
| `list_page_types` | What types exist, with a one-line description each. |
| `get_page_type_schema` | Allowed sections, required/optional, min/max repeats, fields per section. |
| `create_landing_page` | Takes title, type and an ordered array of sections. Validates, builds blocks, saves a **draft**, returns edit and preview links. |

**Deliberately not exposed:** raw HTML or block markup, CSS or styles, theme files, publish, delete, and updating existing pages (by requirement, editing happens in the editor). Each one would let the AI break consistency or cause harm.

### 3.3 Consistency regardless of what the AI attempts
- The AI sends **data, not markup**. The plugin generates all block markup from server-side templates.
- Every request is validated against the page type schema: unknown sections, missing required fields, too many repeats and wrong types are rejected with clear errors the AI can fix and retry on.
- All text is sanitized; no HTML gets through.
- Design comes from the theme: sections use core blocks (Group, Columns, Heading, Paragraph, Buttons, Image) and `theme.json` presets (`var(--wp--preset--color--...)`, spacing, font sizes). No hard-coded colors or fonts, so pages follow any block theme.

### 3.4 Flexible page shapes
- A page type = config: list of allowed section types, each with `required`, `min`, `max`, and optional ordering rules.
- A page = ordered array of section instances, so a section can be skipped or appear more than once.
- Adding a page type = adding one config file (and new sections only if needed).

**Why page types and not a free-form section library?** A simpler option is one shared library of sections that the AI combines however it likes. I chose page types on purpose:
- A type carries rules a free-form page can't, such as "a webinar page must have a date and a registration button" or "the CTA is always last".
- The AI can only pick from what a type allows, which is what keeps pages consistent no matter what it tries.
- The brief describes "several types of landing pages", so types match how marketing already thinks.
- Sections can still be shared between types, so a new type usually reuses existing sections and is just one JSON file.

The cost is that a page marketing wants but no type covers needs a developer to add that type. If that happens often, the next step is an admin screen for page types (see section 5).

### 3.5 Easy editing for non-technical users
- The output is normal core blocks, so everything is click-to-edit.
- Each section is wrapped in a Group with `templateLock: "contentOnly"`, so people change text and images without breaking layout. They can still unlock a section if they need to.
- Sections are also registered as block patterns, so people can add another section by hand.

## 4. Prototype scope
**Built:** one page type (`webinar`), 4–5 sections (one repeatable, one optional), the three tools, validation with clear errors the AI can fix, draft creation with an edit link, tested on Twenty Twenty-Four and Twenty Twenty-Five.

**Build order:** plugin skeleton and one ability visible in Claude, then the schema, then one section (Hero) plus `create_landing_page` for a first end-to-end draft. After that point there is always something to submit; the rest of the sections, validation messages, README and demo are additions.

**Left out, and why:**
- Admin UI for page types: config is enough for a developer-owned registry. Next step if marketing should add types.
- Images / media library: content quality is out of scope; placeholders are editable.
- Updating existing pages: editing happens in the editor by requirement.
- Automated tests, i18n, rate limiting: production concerns, listed as next steps.

## 5. What I'd do next
1. More page types and sections.
2. Media library search tool.
3. Admin screen for page types.
4. Tests (schema validation, block output).
5. Usage logging (who generated what).

## 6. Walkthrough prep
- What happens when the AI sends an invalid section? (Show the error and the AI retrying.)
- How do you add a new page type? (Show adding one JSON file.)

## 7. Running it
`wp-env start`, create an Application Password, connect your AI client to `/wp-json/mcp/mcp-adapter-default-server`. (Details in README.)
