---
name: visual-testing
description: 'Look at the rendered site in a real browser through the Playwright MCP server: screenshot pages at desktop, tablet and mobile widths, confirm Elementor layouts actually render as designed, check responsive behaviour, and verify front-end changes instead of assuming them. Use after creating or editing an Elementor page, after any CSS, theme or layout change, when asked whether something looks right or renders correctly, or when debugging a page that appears broken.'
---

# Visual testing with Playwright

## When to use

- After creating or editing an Elementor page, before reporting it as done
- After any CSS, theme, template, or layout change
- When asked to check appearance, spacing, alignment, or responsive behaviour
- When debugging "it renders wrong" — look at it before theorising about it

## The site

The `playwright` MCP server runs on the host machine, so browse the host URL from `.env`
(`SITE_URL`, for example `http://localhost:8888`). Container hostnames such as `wordpress`
will not resolve from the browser.

## Procedure

1. **Resolve the URL.** For a page you just created, prefer the `preview_url` the tool
   returned. For any other page, find its id with `__KIT__/bin/wpdev wp post list` and use
   `http://localhost:<WP_PORT>/?page_id=<id>`. Also check the post is published — a draft
   returns 404 to an anonymous browser.
2. **Navigate** to it with the browser tools the session exposes — whichever browser they drive,
   it must be the one you then screenshot and close.
3. **Screenshot at three widths.** Elementor layouts are responsive, and a desktop
   screenshot hides most of the breakage:
   - desktop — 1440 x 900
   - tablet — 768 x 1024
   - mobile — 390 x 844
4. **Read the screenshots back** and describe what is actually there: is the hero dark, is
   the three-column grid three columns, has anything overflowed, is any text invisible.
5. **Fix, then re-shoot.** Never report success from a screenshot taken before your last
   change.
6. **Close the page when you are done with it** — see *Leaving nothing stale* below.

## Leaving nothing stale

A page is not evidence on its own, and an old page is worse than none: it looks current.

- **Re-open every round.** Page ids do not survive. A page opened earlier in the conversation,
  or one the client re-attaches when a session starts, is usually already gone, and a page that
  still answers may be holding a render from before your last change. Navigate to the URL again
  in this session and shoot after the change you are about to report — never before.
- **One page per run.** Open once, reuse for all three widths and for every re-shoot. A tab per
  viewport is what leaves a trail of stale pages behind.
- **Close it at the end.** Use whatever close the session's tools expose; if there is none, say
  which URLs you left open so the user can close them, and do not leave the last screenshot as
  the only trace of the last edit.
- **Do not assume the code-execution tool can close it.** That tool drives a different browser
  from the navigate/screenshot tools (`run_playwright_code` answers "Page not found" for a page
  id those tools read fine), so a `page.close()` there is not a cleanup step you have.

## What to look for

- **Unstyled page.** Nearly always Elementor's generated CSS being stale. Call the
  `clear-elementor-cache` ability, reload, and shoot again.
- **A stale render** — the shot is byte-identical to the previous one, or still shows the old
  price, stock badge or copy. Re-navigate and shoot again before drawing any conclusion from
  it; a stale render has cost real time on this project already.
- **Horizontal scrollbar**, i.e. content wider than the viewport. Usually a fixed width or
  stray padding on a container.
- **Collapsed or wrapped columns** at tablet where you expect three across.
- **Invisible text** — colour against background, most likely on hero and CTA bands where the
  defaults are light text on a dark background.
- **Broken or wrongly cropped images**, which usually means the `image_size` setting.
- **Missing header and footer.** Expected when the page template is `canvas`; a bug otherwise.

## Limits — state these rather than overclaiming

- A screenshot proves what one viewport looked like at one moment. It is not a test suite and
  it does not prove correctness on other pages.
- Judging whether a design looks *good* is the user's call. Describe what you observe and let
  them decide.
- If Playwright cannot launch, the browser is most likely not installed. Run
  `npx playwright install chromium` and report that, rather than falling back to guessing from
  the HTML source.
- Screenshots are cheap; use them liberally, but say which ones you actually looked at.
