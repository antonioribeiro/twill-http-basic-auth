const quote = (file) => JSON.stringify(file);
const isBlade = (file) => file.endsWith(".blade.php");

// The commit gate: PHPStan (level max), then PHP CS Fixer + Prettier, then a lint of every PHP file the formatter touched
// (Prettier's PHP parser has dropped parentheses PHP needs before, and a file that no longer parses must fail the commit).
export default {
    "package.json": "npx sort-package-json",
    "**/*": "sh tools/linters.sh conflict-markers",
    "**/*.php": (files) => {
        const php = files.filter((file) => !isBlade(file));

        return [
            ...(php.length > 0
                ? [`sh tools/linters.sh phpstan ${php.map(quote).join(" ")}`]
                : []),
            `sh tools/linters.sh prettify ${files.map(quote).join(" ")}`,
            ...php.map((file) => `php -l ${quote(file)}`),
        ];
    },
};
