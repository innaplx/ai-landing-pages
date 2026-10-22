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
| Which page types exist today, and what sections do they use? | One example type, `webinar`, with Hero, Speaker (repeatable), Agenda (optional), FAQ (optional, repeatable) and CTA sections. |
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
The plugin registers three abilities. The MCP Adapter's default server doesn't turn each one into its own MCP tool; it exposes a few generic tools (discover abilities, get ability info, execute ability), and the AI finds and runs these three through them.

| Ability | Purpose |
|---|---|
| `ai-landing-pages/list-page-types` | What types exist: slug, label and a one-line description. |
| `ai-landing-pages/get-page-type-schema` | For one type: the allowed sections, min/max repeats, position rules, and the fields of each section with their limits. |
| `ai-landing-pages/create-landing-page` | Takes `page_type`, `title` and an ordered array of sections. Validates, builds blocks, saves a draft, returns edit and preview links. |

All three need the `edit_pages` capability, so they run with the rights of the connected WordPress user.

The `webinar` schema as built:

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
- The plugin ships one small CSS file for layout only: spacing, alignment, the speaker grid. Values come from the theme's presets (`var(--wp--preset--spacing--...)`, wide size, root padding), and the light background on the hero and CTA is `color-mix()` over `currentColor`, so it's a tint of whatever text color the theme uses. No fixed colors, no fonts. In short: the plugin owns structure, the theme owns the look.
- The hero holds the page's H1, so the plugin picks the theme's "no title" page template when it has one, to avoid a doubled title. If the theme has none, the default template is used.

### 3.4 Flexible page shapes
- A page type = config: list of allowed section types, each with `required`, `min`, `max`, and optional ordering rules.
- A page = ordered array of section instances, so a section can be skipped or appear more than once.
- Adding a page type = adding one config file (and new sections only if needed).

**Why page types and not a free-form section library?** A simpler option is one shared library of sections that the AI combines however it likes. I chose page types on purpose:
- A type carries rules a free-form page can't, such as "a webinar page must have a date and a registration button" or "the CTA is always last".
- The AI can only pick from what a type allows, which is what keeps pages consistent no matter what it tries.
- The brief describes "several types of landing pages", so types match how marketing already thinks.
- Sections can still be shared between types, so a new type usually reuses existing sections and is just one JSON file.

This isn't really one or the other. The sections are a shared library, and a type is a recipe over it: which sections, how many, in what order. A conference page or a PDF-download page is one more JSON file that reuses `hero` and `cta`, and maybe adds one new section such as a download form. If marketing wants more freedom, a `generic` type that allows every section with loose rules is a config change, not an architecture change.

The brief says marketing shouldn't need developers "for every page", not "ever". With types, a developer is only needed when a new type is added, which is much rarer than a new page. The cost is that a page marketing wants but no type covers needs a developer to add that type. If that happens often, the next step is an admin screen for page types (see section 5).

### 3.5 Easy editing for non-technical users
- The output is normal core blocks, so everything is click-to-edit.
- Each section is wrapped in a Group with `templateLock: "contentOnly"`, so people change text and images without breaking layout. They can still unlock a section if they need to.
- Sections are only locked inside, so people can still add their own blocks between them in the editor.

## 4. Prototype scope
**Built:**
- The three abilities, exposed over MCP through the official adapter.
- One page type, `webinar`, loaded from `page-types/webinar.json`: five section types, with required, optional and repeatable sections and first/last position rules.
- Server-side validation that collects every problem in one error the AI can fix and retry on.
- Block output from core blocks, each section a `contentOnly` Group, saved as a draft.
- Layout-only CSS on theme presets, and the "no title" template when the theme has one.
- Tested end to end from Claude Desktop, on Twenty Twenty-Five and Twenty Twenty-Four. The editor opens the pages with no block validation warnings.

**Build order:** plugin skeleton and one ability visible in Claude, then the schema, then one section (Hero) plus `create-landing-page` for a first end-to-end draft. After that point there was always something to submit; the rest of the sections, validation messages, styling, README and demo came on top.

**Behaviour test:** I asked Claude for a webinar page with a testimonials section and a Free vs Pro pricing table, neither of which the type has. It built the page without them, said it can't add HTML to get around the schema, and wouldn't make up customer quotes. It suggested adding those parts by hand in the editor. The rules held before validation even had to step in.

**Left out, and why:**
- Admin UI for page types: config files are enough for a developer-owned registry. Next step if marketing should add types.
- Images and media library: content quality is out of scope, and images can be added in the editor.
- Updating existing pages: by the brief, editing happens in the editor. Claude offers to create a new draft instead.
- A `generic` page type: easy to add as one JSON file, left out to keep the example strict.
- OAuth and production auth: the prototype uses Application Passwords. Production would be an org connector with OAuth (section 3.1).
- Automated tests, i18n, rate limiting: production concerns.
- Classic themes: core blocks should render there, but I didn't verify it, and the preset fallbacks would need checking.

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
Setup, connecting Claude Desktop and example prompts are in [README.md](README.md).
