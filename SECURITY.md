# Security Policy

## Supported versions

Security fixes are released for the latest `0.x` line only.

| Version | Supported |
| :--- | :--- |
| 0.4.x | Yes |
| < 0.4.0 | No |

## Reporting a vulnerability

Report vulnerabilities privately — never via public issue. Use [GitHub Security Advisories](https://github.com/JovianOps/phptui/security/advisories/new).

## Scope

In scope: terminal injection via unescaped values in OSC/ANSI output, sandbox escapes in the testing harness, path traversal in discovery (`../` in user-supplied paths), unsafe instantiation of discovered classes.

This library writes ANSI escape sequences by design — that is not a vulnerability. Findings in consuming applications' own form definitions are out of scope.
