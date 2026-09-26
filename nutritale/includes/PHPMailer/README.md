# Vendored PHPMailer

`PHPMailer.php`, `SMTP.php`, and `Exception.php` are PHPMailer v7.1.1
(https://github.com/PHPMailer/PHPMailer), used unmodified. Vendored as
plain files rather than pulled in via Composer, since this project doesn't
use Composer anywhere else (see `includes/oauth.php`'s header comment for
the same reasoning applied to Google/Facebook sign-in).

Licensed under LGPL 2.1 (see `LICENSE` in this directory) — used here as a
library, unmodified, which LGPL permits freely.

To upgrade: replace these three files with the same three files from a
newer tagged release, keeping the version pinned (never point at `master`).
