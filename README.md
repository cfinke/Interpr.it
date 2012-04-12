On Interpr.it, developers upload their extensions, translators localize them into their native language, and then developers can download the new locale files to use in their next extension update.

Interpr.it for Developers
=========================
Developer interaction is limited to the upload and download of locale files. In fact, developers can interact with Interpr.it completely from the command line via the /api/upload and /api/download API methods. (There are also API methods for translating messages and retrieving the translation history of a message.)

Interpr.it for Translators
==========================
Once an extension has been uploaded to Interpr.it, a status page is generated that shows the progress made on all of the locales.

Each locale code links to a translation page, which is a listing of all of the messages (or "phrases" or "strings") used in the extension.

Sign in/out is tied to your Google account, so you don’t have to create another username and password; it seemed like a natural fit for a site originally focused on Google Chrome extensions.

The Interpr.it site itself is localized using itself. (Interpr.it obviously isn’t a browser extension, but it does use Google Chrome-style JSON locale files, so it’s compatible with Interpr.it’s translation system.) To access a localized version of Interpr.it, select a locale code from the menu in top-right corner of the website, or manually type a URL like es.interpr.it.