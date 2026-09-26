# composable-jev-demo

The page (`resources/page.html`) and the PHP server (`src/`, `public/`) for building composable-jev
graphs in a browser. The library is composable-jev, `phox/composable-jev` on Packagist; a
change to how graphs run belongs there, not here. The separate jev-perceptron project serves the same
page from its `src/jev_perceptron/static/index.html`, so a change to one goes to both.

## Code

- PHP 8.3, strict types, PSR-12. `pint.json` at the project root is authoritative.
- Pest tests for every behaviour. Tests run offline: `Tests\Fakes\FakeTypeSafe` is the SDK's
  transport. No test calls a paid API.
- The page's request and catalog keys (`graph`, `graphs`, `node`, `contexts`, `context`) are its
  contract with both servers; a change to one changes all three.

## Writing

- A comment states what the code is now, never what it was. Git holds history.
- A comment carries a fact the code cannot: a wire fact, a trap, an invariant, a why-not, the
  measurement behind a decision. If the name and type already say it, delete it.
- One fact per sentence. No closing epigram, no section banners, no docblock restating a signature.
- No temporal markers ("now", "currently", "still"), no "actually", "simply", "genuinely". Use
  " - " for a dash, and "instead of" for "rather than".
- Commit messages: imperative subject describing the behaviour change; the body says what was
  broken and what the fix does.
