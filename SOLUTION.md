# AI Landing Page Generator: Solution Document

## 1. How I understood the request
Marketing wants to describe a landing page in plain language, from their own AI client, and get a page on the WordPress site that:
- looks like the rest of the site (whatever theme it runs),
- can be edited like any other page in the block editor,
- follows the rules of its page type, while sections can be optional or repeated,
- can grow to new page types without a rebuild.

What I read as the core problem: the AI is creative and unpredictable, and the site has to stay consistent anyway. So the AI decides content and composition, and the plugin decides structure and design.

Time spent: about 5–6 hours in total, roughly a third on this document and the rest on the prototype.

## 2. Open questions and my assumptions
| Question I'd ask stakeholders | Assumption I made |
|---|---|
| Who approves a page before it goes live? | Pages are always created as drafts. A person reviews and publishes. |
| Which page types exist today, and what sections do they use? | One example type, `webinar`, with Hero, Speaker (repeatable), Agenda (optional), FAQ (optional, repeatable) and CTA sections. |
| Who adds new page types? | A developer, through a JSON file (code only for a new kind of section). An admin UI for this is a later step. |
| Should the AI update existing pages? | No. It only creates pages. Editing happens in the block editor. |
| Images? | Placeholders the team swaps in the editor. Media library support is a next step. |
| Is the live theme a block theme or a classic theme? | Block theme with `theme.json`. Sections use core blocks only, which also render in classic themes, but presets would need a fallback there. |
| Where do forms and CTAs connect (CRM, Marketo)? | Out of scope. CTAs are plain links. |
| SEO, multiple languages / RTL, analytics? | Title and slug only, single language. Meta description and the rest wait for review in the editor (and whichever SEO plugin the site already runs). |
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
Three abilities. The adapter's default server exposes them through its generic tools (discover, get info, execute).

| Ability | Purpose |
|---|---|
| `ai-landing-pages/list-page-types` | What types exist: slug, label and a one-line description. |
| `ai-landing-pages/get-page-type-schema` | For one type: the allowed sections, min/max repeats, position rules, and the fields of each section with their limits. |
| `ai-landing-pages/create-landing-page` | Takes `page_type`, `title` and an ordered array of sections. Validates, builds blocks, saves a draft, returns edit and preview links. |

All three need `edit_pages`, so they run with the connected user's rights. The `webinar` schema:

| Section | Count | Rule |
|---|---|---|
| `hero` | exactly 1 | must be first; headline, date and time, button |
| `speaker` | 1–4 | one speaker per section; consecutive speakers are shown side by side in a grid |
| `agenda` | 0–1 | heading and 2–8 items |
| `faq` | 0–8 | one question and answer per section |
| `cta` | exactly 1 | must be last |

**Deliberately not exposed:** raw HTML or block markup, CSS or styles, theme files, publish, delete, and updating existing pages (by requirement, editing happens in the editor). Each one would let the AI break consistency or cause harm.

### 3.3 Consistency regardless of what the AI attempts
- The AI sends **data, not markup**. The plugin generates all block markup from server-side templates.
- Every request is validated against the page type schema: unknown sections, missing required fields, too many repeats and wrong types are rejected with clear errors the AI can fix and retry on.
- All text is sanitized; no HTML gets through.
- Design comes from the theme. Sections use core blocks only (Group, Heading, Paragraph, List, Buttons, Details), with no inline styles.
- One small CSS file handles layout only (spacing, alignment, the speaker grid), using the theme's presets. The hero and CTA background is a tint of the theme's text color. No fixed colors or fonts, and it loads only on pages the plugin created. The plugin owns structure, the theme owns the look.
- The hero holds the page's H1, so the plugin picks the theme's "no title" page template when it has one, to avoid a doubled title. If the theme has none, the default template is used.

### 3.4 Flexible page shapes
- A page type is config: allowed sections, `min`/`max` for each, optional first/last rules.
- A page is an ordered array of sections, so a section can be skipped or repeated.
- Why types and not one free-form section library: a type carries rules a free page can't ("a webinar needs a date and a registration button", "the CTA is last"), and the brief itself talks about "several types of landing pages".
- Sections are still shared, so a type is a recipe over them. A conference or PDF-download page is one more JSON file reusing `hero` and `cta`; only a new kind of section needs code.
- More freedom later is a config change: a `generic` type that allows every section with loose rules.
- The cost: a page no type covers needs a developer once, to add the type. Marketing still doesn't need one "for every page".

### 3.5 Easy editing for non-technical users
- The output is normal core blocks, so everything is click-to-edit.
- Each section is a Group with `templateLock: "contentOnly"`: people change text without breaking layout, and can still add their own blocks between sections.

## 4. Prototype scope
**Built:**
- The three abilities, exposed over MCP through the official adapter.
- One page type, `webinar`, loaded from `page-types/webinar.json`: five section types, with required, optional and repeatable sections and first/last position rules.
- Server-side validation that collects every problem in one error the AI can fix and retry on.
- Block output from core blocks, each section a `contentOnly` Group, saved as a draft.
- Layout-only CSS on theme presets, and the "no title" template when the theme has one.
- Tested end to end from Claude Desktop, on Twenty Twenty-Five and Twenty Twenty-Four. The editor opens the pages with no block validation warnings.

**Build order:** one ability visible in Claude, then the schema, then Hero alone end to end. From there I always had something to submit, and the rest came on top.

**Behaviour test:** I asked Claude for a webinar page with testimonials and a pricing table, which the type doesn't have. It built the page without them, said it can't add HTML to get around the schema, and suggested adding them in the editor.

**Left out, and why:**
- Admin UI for page types: config files are enough for a developer-owned registry. Next step if marketing should add types.
- Images and media library: content quality is out of scope, and images can be added in the editor.
- Updating existing pages: by the brief, editing happens in the editor. Claude offers to create a new draft instead.
- OAuth and production auth: the prototype uses Application Passwords. Production would be an org connector with OAuth (section 3.1).
- Automated tests, i18n, rate limiting, and a `generic` type: production or later concerns.
- Classic themes: core blocks should render there, but I didn't verify it, and the preset fallbacks would need checking.

## 5. What I'd do next
More page types and sections, a media library tool, an admin screen for page types, optional SEO fields once we know which SEO plugin the site uses, tests for validation and block output, and usage logging.

## 6. Running it
Setup, connecting Claude Desktop and example prompts are in [README.md](README.md).
