# Kirby Help View

A [Kirby](https://getkirby.com/) plugin that adds a help view to the Panel for your clients.

## Features

- Custom Panel area with help articles
- Supports categories with nested articles
- KirbyText in articles (links, images, formatting)
- Render real Panel buttons and icons inside articles
- Icons and colors for each article card
- Previous/next navigation between articles
- Breadcrumbs for nested articles
- Multilanguage support

![Screenshot of the overview with cards for help articles](https://github.com/user-attachments/assets/996aa373-e4ad-4104-9c36-0401ff12d59d)
![Screenshot of an article view](https://github.com/user-attachments/assets/5f389432-cefe-4c6c-813b-1f9f0035b5fd)

## Content Structure

Create a `site/help` folder with your articles:

```
site/help/
├── 1_getting-started/
│   ├── article.txt
│   ├── article.de.txt
│   └── article.ja.txt
├── 2_editing/
│   ├── category.txt
│   ├── 1_text/article.txt
│   └── 2_images/article.txt
└── 3_settings/article.txt
```

- Number prefixes (`1_`, `2_`) control the order
- Folders with `article.txt` become articles
- Folders without `article.txt` but with subfolders containing articles become categories. You can add an optional `category.txt` to overwrite the title.
- For multilanguage support, add language codes to filenames (e.g. `article.de.txt`). Depending on the user's Panel language, the appropriate article version will be shown.

Seems familiar, doesn't it? I tried to keep it close to Kirby's content structure while also keeping it out of the actual site content so you don't accidentally expose help articles on the public site.

### Example article

```yaml
# site/help/1_getting-started/article.txt

Title: Getting Started

----

Icon: book

----

Color: blue-600

----

Back: blue-200

----

Text:

## Welcome

Your help content here with **KirbyText** support.

(image: screenshot.png)
```

You can define an icon from Kirby's [icon set](https://lab.getkirby.com/public/lab/basics/icons/1_iconset) and colors from the Kirby [color variables](https://lab.getkirby.com/public/lab/basics/design/colors).

### Example category

```yaml
# site/help/2_editing/category.txt

Title: Editing Content
```

If you don't add a `category.txt`, the folder name will be used as the title (e.g. "2_editing" → "Editing"). If you're not having to deal with German Ümläuts (or other fancy characters) in your folder names, don't worry about it.

## Panel buttons and icons

To make your documentation match what clients actually see, you can drop `<k-button>` and `<k-icon>` straight into your articles. They render as the real Panel buttons and icons, reusing Kirby's own styles and icon set, so they always look like the real thing.

### Buttons

```html
<k-button icon="check" theme="positive">Save</k-button>
```

Because they render as Kirby's own buttons, the [Kirby Lab](https://lab.getkirby.com) is the live, visual reference for the icons, themes, variants and sizes you can use. All attributes are optional:

- `icon` – any icon name from the [icon set](https://lab.getkirby.com/public/lab/basics/icons/1_iconset)
- `theme` – a button [theme](https://lab.getkirby.com/public/lab/components/buttons/2_themes) like `positive`, `negative`, `info` or `notice`. For page status buttons use the `-icon` variants: `negative-icon` (draft), `info-icon` (unlisted), `positive-icon` (listed).
- `variant` – [`filled`](https://lab.getkirby.com/public/lab/components/buttons/1_variants) (default) or `dimmed`
- `size` – [`xs`, `sm`, `md`, `lg`](https://lab.getkirby.com/public/lab/components/buttons/3_sizes) (default `md`)
- `link` – turns the button into a link. Reference a file in the article's folder by name (e.g. `link="template.zip"`), just like the `(image:)` and `(file:)` tags. Relative URLs (e.g. `/panel/site`), `mailto:`, `tel:` and external links also work; external links open in a new tab.
- `download` – on a button that links to a file, saves it under its original filename instead of opening it in the browser
- `title` – accessible label for an icon-only button, e.g. `<k-button icon="add" title="Add"></k-button>` (matches Kirby's `k-button`; not needed when there's visible text)

Ideally put buttons on their own line. Avoid placing one in the middle of a sentence – a button is taller than your text and will disrupt the line height.

```html
<k-button icon="add">Add</k-button>
<k-button icon="trash" theme="negative">Delete</k-button>
```

### Icons

Icons are inline and decorative by default (`aria-hidden`). When an icon carries meaning, add `alt` so screen readers announce it:

```html
Click the <k-icon name="add" alt="Add" /> button to add a block.
```

Use any name from the [icon set](https://lab.getkirby.com/public/lab/basics/icons/1_iconset).

## Options

```php
// site/config/config.php
return [
	'medienbaecker.help-view' => [
		'root' => '/path/to/custom/help/folder'
	]
];
```

## Notes

- The help menu item only appears if the `site/help` folder exists.
- Help articles are trusted content (like your templates) – any HTML in them is rendered as-is.

## Requirements

Kirby 5.2.0 or higher (I'm using the new `Str::label()` method)

## Installation

### Composer

```
composer require medienbaecker/kirby-help-view
```

### Manual

Download and copy this repository to `site/plugins/kirby-help-view`.

## Credits

[kirby-helpsection](https://github.com/amteich/kirby-helpsection) by amteich was the original inspiration for this plugin. I've been using it for years and my clients loved it. Unfortunately it has not been updated since 2021 and is not compatible with Kirby 5, so I created this new version from scratch, trying to stick to core components as much as possible.
