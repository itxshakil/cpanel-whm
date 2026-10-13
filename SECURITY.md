# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 0.x | yes, the latest minor release only |

Fixes land in the latest 0.x release. From 1.0, the latest major release gets security fixes, and the previous major gets them for six months after a new major ships.

## Reporting a vulnerability

Please don't open a public issue. Report it privately through
[GitHub security advisories](https://github.com/itxshakil/cpanel-whm/security/advisories/new),
or email itxshakil@gmail.com.

You'll get an acknowledgement within 72 hours. Please allow time for a fix before disclosing.

This package holds WHM API tokens, often root ones. Anything that lets a token, an account password or a login session reach a log, a URL, an exception message, an event or command output is a vulnerability.
