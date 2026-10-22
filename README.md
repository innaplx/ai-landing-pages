# AI Landing Pages

A WordPress plugin that lets marketing create landing pages from their own AI client, like Claude, over MCP.
The AI sends content only. The plugin checks it against a page type schema, builds the page from core blocks and saves it as a draft that looks like the active theme and is edited in the normal block editor.

The design and the reasoning behind it are in [SOLUTION.md](SOLUTION.md).

## Requirements
- WordPress 6.9 or later (the Abilities API is in core). Tested on 7.1 with Twenty Twenty-Five and Twenty Twenty-Four.
- The [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin, active.
- Node.js, for the `@automattic/mcp-wordpress-remote` proxy that Claude Desktop runs.
- Claude Desktop, or any other MCP client.

## Install
1. Copy this folder to `wp-content/plugins/ai-landing-pages`.
2. Install and activate the MCP Adapter plugin.
3. Activate AI Landing Pages.

The MCP endpoint is then `http://YOUR-SITE/wp-json/mcp/mcp-adapter-default-server`.

## Create a user and an Application Password
1. Create a WordPress user with the Editor role (any role with `edit_pages` works). Each person should use their own user, so pages are attributed to them.
2. Go to Users > Profile > Application Passwords, add a name such as "Claude", and copy the password. It is shown only once.

On a local site without HTTPS, Application Passwords may need to be enabled first (for example, set `WP_ENVIRONMENT_TYPE` to `local`).

## Connect Claude Desktop
Open Settings > Developer > Edit Config and add:

```json
{
  "mcpServers": {
    "wordpress-ai-landing": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "http://YOUR-SITE/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-wp-username",
        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx",
        "OAUTH_ENABLED": "false"
      }
    }
  }
}
```

Restart Claude Desktop fully (quit it from the tray, not just the window). The server should show as running under Settings > Developer.

On Windows, use the full path to `npx.cmd` as the command, for example `"C:\\Program Files\\nodejs\\npx.cmd"`, and save the file as UTF-8 without BOM. Notepad sometimes adds one, and then Claude can't read the config.

## Try it
```
What landing page types can you create on our WordPress site?
```

```
Create a webinar landing page for "Scaling Onboarding with AI" on November 18 at 17:00 CET.
Speakers: Dana Levi, Head of Product Adoption, and Tom Cohen, Solutions Architect.
Registration link: https://example.com/register. Add an agenda with 4 points and 3 FAQ questions.
```

```
Create the same webinar page, but add a testimonials section and a pricing table.
```

The last one asks for sections the type doesn't have. Claude should build the page without them and say why.

Every page is saved as a draft. Claude returns an edit link and a preview link; review and publish in WordPress.

## Add a page type
Drop a JSON file in `page-types/`. It shows up in `list-page-types` right away, with no code change.

```json
{
  "slug": "product-launch",
  "label": "Product launch",
  "description": "One line the AI reads to pick the right type.",
  "sections": {
    "hero": {
      "label": "Hero",
      "description": "What this section is for.",
      "min": 1, "max": 1, "position": "first",
      "fields": {
        "headline":   { "type": "string", "required": true, "max_length": 80 },
        "button_url": { "type": "url", "required": true }
      }
    }
  }
}
```

- `sections`: the section types the page may use, keyed by section name.
- `min` / `max`: how many times the section may appear. `min: 0` makes it optional, `max` above 1 makes it repeatable.
- `position`: optional, `first` or `last`.
- `fields`: each field has a `type` (`string`, `url` or `list`), `required`, and limits: `max_length`, and `min_items` / `max_items` for lists.

Section names must have a renderer in `includes/class-page-builder.php` (`render_<name>`). Existing ones are `hero`, `speaker`, `agenda`, `faq` and `cta`. A type that reuses one should keep that section's fields, since the renderer expects them (the example above is shortened). A type that only reuses existing sections is just the JSON file; a new kind of section needs one small render method.

## Project structure
```
ai-landing-pages.php          Plugin bootstrap, checks for the Abilities API, loads the CSS
includes/
  class-abilities.php         The three abilities exposed over MCP
  class-registry.php          Loads page types from page-types/*.json
  class-validator.php         Checks the AI's input against the schema and sanitizes it
  class-page-builder.php      Turns sections into core blocks and saves the draft
page-types/
  webinar.json                The example page type
assets/
  alp.css                     Layout-only styles built on theme presets
SOLUTION.md                   Solution document
```
