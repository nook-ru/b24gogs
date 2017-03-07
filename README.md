b24gogs
========

Web-hook handler for [Gogs](https://gogs.io/) to integrate with **Bitrix24 Self-hosted**. Adds pushed commits as comments to Bitrix24 tasks by their number in commit message.

How to use
==========

1. You need to have both working *Gogs* and *Bitrix24*.
2. Put "hooks" folder anywhere within document root of your *Bitrix24*.
3. All users MUST have same emails in *Gogs* and *Bitrix24*.
4. Choose **WebHooks** in a project settings in *Gogs*, and fill in an URL for `commithandler.php`
5. *Optionally* specify hook's Secret in `GOGS_SECRET` const
5. Enjoy :)

Distribution
============

Please feel free to modify and use (GPLv2).
Pull requests are strongly appreciated.
