import { execFileSync } from "node:child_process";

const trackedFiles = execFileSync("git", ["ls-files", "-z"], {
    encoding: "utf8",
})
    .split("\0")
    .filter(Boolean);

const forbiddenPatterns = [
    // Private runtime catalog data that must not ship in the open-source repo.
    /^public\/phone\//i,
    /^storage\/app\/private\/phone-data\//i,
    // One-off local audit output is not part of the distributable source tree.
    /^artifacts\//i,
    /^WORKMODE_AUDIT_REPORT\.md$/i,
    // Environment files (allow .env.example and .env.*.example templates).
    /(^|\/)\.env(?:$|\.(?!.*example$))/i,
    // Databases, data exports and spreadsheets.
    /\.(csv|db|dump|sqlite|sqlite3|sql|xls|xlsx)$/i,
    // Keys, certificates and key stores.
    /\.(pem|key|p12|pfx)$/i,
    /(^|\/)id_(rsa|dsa|ecdsa|ed25519)$/i,
    // Package-manager / cloud credentials.
    /(^|\/)\.npmrc$/i,
    /(^|\/)auth\.json$/i,
    /(^|\/)credentials(\.[^/]+)?$/i,
    // Logs and backups.
    /\.(log|bak|backup|old|tgz)$/i,
    /\.tar\.gz$/i,
];

const violations = trackedFiles.filter((file) =>
    forbiddenPatterns.some((pattern) => pattern.test(file)),
);

if (violations.length > 0) {
    console.error("Private or sensitive files must not be tracked:");
    violations.forEach((file) => console.error(`- ${file}`));
    process.exit(1);
}

console.log("Open-source boundary check passed.");
