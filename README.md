# Multi WordPress Search

A WordPress plugin that replaces the default search form with a custom search engine that queries **multiple WordPress sites** simultaneously via the official [WordPress REST API](https://developer.wordpress.org/rest-api/).

## Features

- Replaces the native WordPress search form site-wide (via the `get_search_form` filter).
- Queries any number of remote WordPress sites using `/wp-json/wp/v2/search`.
- Displays a live **as-you-type** dropdown with results from all configured sites.
- Full-page results view inherits the active theme's header and footer.
- Simple admin settings page (**Settings → Multi WP Search**) to manage the list of sites.
- `[multi_wordpress_search]` shortcode for placing the search form anywhere.
- Clean uninstall: all plugin data is removed when the plugin is deleted.

## Installation

1. Copy the `MultiWordpressSearch` directory into `wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Go to **Settings → Multi WP Search** and add the base URLs of the WordPress sites you want to search (e.g. `https://example.com`).
4. The default search form on your site is now replaced automatically.

## Usage

### Automatic replacement

Once activated and configured, every call to `get_search_form()` in your theme is intercepted and returns the Multi WordPress Search form instead.

### Shortcode

Place the search form anywhere using:

```
[multi_wordpress_search placeholder="Search our network…"]
```

### REST API endpoint (AJAX)

The plugin exposes a WordPress AJAX action for live search:

```
GET /wp-admin/admin-ajax.php?action=mws_search&nonce=<nonce>&query=<term>
```

Returns a JSON response:

```json
{
  "success": true,
  "data": [
    {
      "title": "Post title",
      "url": "https://example.com/post-slug/",
      "excerpt": "Short excerpt…",
      "type": "post",
      "site_name": "Example Site",
      "site_url": "https://example.com/"
    }
  ]
}
```

## File structure

```
multi-wordpress-search.php   ← Main plugin file (headers + bootstrap)
uninstall.php                ← Cleanup on deletion
includes/
  class-mws-api-client.php   ← Queries remote WordPress REST APIs
  class-mws-search-form.php  ← Renders & intercepts the search form
admin/
  class-mws-admin.php        ← Settings page registration
  admin-page.php             ← Settings page partial (site list)
  js/mws-admin.js            ← Dynamic add/remove site rows
public/
  css/multi-wordpress-search.css  ← Front-end styles
  js/multi-wordpress-search.js    ← Live search + keyboard navigation
templates/
  search-results.php         ← Full-page results template
languages/                   ← POT / translation files (empty by default)
```

## License

CeCILL-B — see <https://cecill.info/licences/Licence_CeCILL-B_V1-en.html>.
