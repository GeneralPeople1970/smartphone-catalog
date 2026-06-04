import { execFileSync } from "node:child_process";

const trackedFiles = execFileSync("git", ["ls-files", "-z"], {
    encoding: "utf8",
})
    .split("\0")
    .filter(Boolean);

const forbiddenPatterns = [
    /^public\/phone\//i,
    /^storage\/app\/private\/phone-data\//i,
    /(^|\/)\.env(?:$|\.(?!example$))/i,
    /\.(csv|db|dump|sqlite|sqlite3|sql|xls|xlsx)$/i,
];

const violations = trackedFiles.filter((file) =>
    forbiddenPatterns.some((pattern) => pattern.test(file)),
);

if (violations.length > 0) {
    console.error("Private runtime data must not be tracked:");
    violations.forEach((file) => console.error(`- ${file}`));
    process.exit(1);
}

console.log("Open-source boundary check passed.");
