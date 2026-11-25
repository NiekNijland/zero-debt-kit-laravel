// Moves TypeScript or JavaScript files from a map file and rewrites every module specifier that names them.
//   node tools/codemods/move-ts.mjs <moves.tsv> [--reverse] [--verify] [--alias @=src] [--roots src,tests]
// The map (templates/pr-description-and-brief.md, "Move map") is one line per file: old path, a tab, new path; a "#export" suffix is ignored.
// Without a flag: git mv each file that is still at its old path (running it again changes nothing), then rewrite the specifiers in
// the tracked files under the roots: relative, alias, import(), require() and mock calls. --reverse swaps the columns: the same
// tool takes a group back out of a finished move. --verify changes nothing and exits 1 when an old path still exists, a new path
// is missing, or a specifier still names an old path.
import { execFileSync } from "node:child_process";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";

const args = process.argv.slice(2);
const flag = (name) => args.includes(name);
const option = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
const mapFile = args.find((arg) => !arg.startsWith("--") && args[args.indexOf(arg) - 1] !== "--alias" && args[args.indexOf(arg) - 1] !== "--roots");
if (!mapFile) {
    console.error("Usage: node tools/codemods/move-ts.mjs <moves.tsv> [--reverse] [--verify] [--alias @=src] [--roots src,tests]");
    process.exit(64);
}
const [aliasName, aliasDir] = option("--alias", "@=src").split("=");
const roots = option("--roots", "src").split(",");
const EXT = /\.(tsx?|jsx?|mjs|cjs|mts|cts)$/;
const bare = (file) => file.replace(EXT, "").replace(/\/index$/, "");

const pairs = readFileSync(mapFile, "utf8")
    .split("\n")
    .filter((line) => line.trim() !== "")
    .map((line) => line.split("\t").map((part) => part.split("#")[0]))
    .map(([from, to]) => (flag("--reverse") ? [to, from] : [from, to]));
const moved = new Map(pairs.map(([from, to]) => [bare(from), bare(to)]));
const origin = new Map(pairs.map(([from, to]) => [to, from]));

const tracked = execFileSync("git", ["ls-files", ...roots], { encoding: "utf8" }).split("\n").filter((file) => EXT.test(file));
const quoted = /((?:from|import\(|require\(|\.mock\(|\.doMock\(|\.importActual\(|import)\s*)(["'])([^"']+)\2/g;
let problems = 0;

const movedNow = new Set();
if (!flag("--verify")) {
    for (const [from, to] of pairs) {
        if (existsSync(from) && !existsSync(to)) {
            mkdirSync(path.dirname(to), { recursive: true });
            execFileSync("git", ["mv", from, to]);
            movedNow.add(to);
        }
    }
}

const files = flag("--verify") ? tracked : execFileSync("git", ["ls-files", ...roots], { encoding: "utf8" }).split("\n").filter((file) => EXT.test(file));
for (const file of files) {
    const text = readFileSync(file, "utf8");
    const was = movedNow.has(file) ? origin.get(file) : file;
    const rewritten = text.replace(quoted, (whole, lead, quote, specifier) => {
        let target;
        if (specifier.startsWith(".")) target = bare(path.posix.normalize(path.posix.join(path.posix.dirname(was), specifier)));
        else if (specifier.startsWith(aliasName + "/")) target = bare(path.posix.join(aliasDir, specifier.slice(aliasName.length + 1)));
        else return whole;
        const after = moved.get(target) ?? target;
        if (flag("--verify")) {
            if (moved.has(target) && moved.get(target) !== target) {
                console.error(`${file}: the specifier ${specifier} still names the old path ${target}`);
                problems += 1;
            }
            return whole;
        }
        if (after === target && was === file) return whole;
        const next = specifier.startsWith(".")
            ? ((rel) => (rel.startsWith(".") ? rel : "./" + rel))(path.posix.relative(path.posix.dirname(file), after))
            : aliasName + "/" + path.posix.relative(aliasDir, after);
        const ext = (specifier.match(EXT) ?? [""])[0];
        return `${lead}${quote}${next}${ext && !EXT.test(next) ? ext : ""}${quote}`;
    });
    if (rewritten !== text) writeFileSync(file, rewritten);
}

if (flag("--verify")) {
    for (const [from, to] of pairs) {
        if (existsSync(from)) { console.error(`${from}: still exists`); problems += 1; }
        if (!existsSync(to)) { console.error(`${to}: missing`); problems += 1; }
    }
    console.log(problems === 0 ? `Move: ${pairs.length} files moved, no specifier names an old path.` : `Move: ${problems} problems.`);
    process.exit(problems === 0 ? 0 : 1);
}
console.log(`Move: ${pairs.length} map lines applied.`);
