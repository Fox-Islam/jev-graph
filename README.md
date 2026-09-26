# JevGraph

Semantic computation engine, deployed [here](https://jevgraph.lexic.cloud/)

A page for building [composable-jev](https://github.com/Fox-Islam/composable-jev) graphs in a
browser, and the PHP server behind it. Add inputs and nodes in the diagram, edit them in the side
panel, and watch each request spin and pulse as Jev answers. The Gates tab asks one gate, weighted
sum or question over `a` and `b`, as a truth table or a sweep.

```bash
composer install
composer serve    # http://127.0.0.1:8765
```

Put your API key in beside the Jev button, and pick whether Jev is reached through TypeSafe or
OpenRouter. The key is kept in that tab's session storage, gone when the tab closes, and goes to
the server with each run, which uses it for that run and does not log or keep it. Without a key,
the server's own is used where it has one, and otherwise only Simulated mode runs, which answers
each node from its rule and cannot answer a question.

## Examples

`examples/` holds graphs to load with Import on the Build tab. Both classify a 5 by 5 black and
white image as a vertical line, a horizontal line, a plus or an L, and both got 40 of 40 test
images right:

- `shape-classifier.json` asks Jev only whether each run of three pixels is dark, 30 questions in
  one request, and works out lines, crossings, corners and the shape with rules in code.
- `shape-classifier-gates.json` has no rules: every node is a question or gate asked of Jev, five
  requests an image and about four times the tokens.

## Settings

Settings come from the environment or a `.env` at the project root. A key set here is spent by
anyone who can reach the page and has not typed one of their own.

| Setting | |
| --- | --- |
| `TYPESAFE_API_KEY` | the server's TypeSafe key, used where the page sends none |
| `OPENROUTER_API_KEY` | the server's OpenRouter key, the same |
| `COMPOSABLE_JEV_HOSTS` | hosts a run may be addressed to, comma-separated; `127.0.0.1,localhost` when unset |
| `COMPOSABLE_JEV_BATCH` | `0` asks each node on its own |
| `COMPOSABLE_JEV_CACHE` | where answers are cached; `.jev-cache` when unset |

A run is refused unless it comes as JSON: another site cannot post JSON here without a CORS
preflight, which this server never approves. Checking the host stops DNS rebinding.

## Hosting

`public/index.php` serves the page behind PHP's built-in server, or any web server that sends
every path to it; `public/.htaccess` does that for Apache, subfolders included. Set
`COMPOSABLE_JEV_HOSTS` to the host it is served on.

Streaming a run holds a request open while it runs, and the 8-bit adder's truth table (65,536
rows) is off. A host with a short request limit runs ticks, which are one short request each, and
cuts off long truth tables.

## Development

```bash
composer test && composer lint
```

