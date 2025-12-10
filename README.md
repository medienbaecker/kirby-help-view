# Kirby Help View

A [Kirby](https://getkirby.com/) plugin that adds a help section to the Panel for your clients.

## Features

- Custom Panel area with help articles
- Supports categories with nested articles
- KirbyText in articles (links, images, formatting)
- Icons and colors for each article card
- Previous/next navigation between articles
- Breadcrumbs for nested articles

![Screenshot of the overview with cards for help articles](https://github.com/user-attachments/assets/996aa373-e4ad-4104-9c36-0401ff12d59d)
![Screenshot of an article view](https://github.com/user-attachments/assets/5f389432-cefe-4c6c-813b-1f9f0035b5fd)

## Content Structure

Create a `site/help` folder with your articles:

```
site/help/
├── 1_getting-started/article.txt
├── 2_editing/
│   ├── category.txt
│   ├── 1_text/article.txt
│   └── 2_images/article.txt
└── 3_settings/article.txt
```

- Number prefixes (`1_`, `2_`) control the order
- Folders with `article.txt` become articles
- Folders without `article.txt` but with subfolders containing articles become categories. You can add an optional `category.txt` to overwrite the title.

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

You can define an icon from Kirby's [icon set](https://getkirby.com/docs/panel/icons) and colors from the Kirby [color variables](https://lab.getkirby.com/public/lab/basics/design/colors).

### Example category

```yaml
# site/help/2_editing/category.txt

Title: Editing Content
```

If you don't add a `category.txt`, the folder name will be used as the title (e.g. "2_editing" → "Editing").

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

- Images in articles are served via an API route _without_ authentication. This means help screenshots are technically accessible without Panel login if someone guesses the URL. For most use cases this is fine since help content isn't sensitive but be aware of this if you include confidential information in help images. If you have a better idea how to handle this, please let me know.
- The help menu item only appears if the `site/help` folder exists.

## Installation

### Composer

```
composer require medienbaecker/kirby-help-view
```

### Manual

Download and copy this repository to `site/plugins/kirby-help-view`.
