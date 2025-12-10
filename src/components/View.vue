<template>
	<k-panel-inside class="k-help-view">
		<template v-if="current" #topbar>
			<k-prev-next :prev="prev" :next="next" />
		</template>

		<k-header>{{ current ? current.title : $t("medienbaecker.help-view.title") }}</k-header>

		<template v-if="current">
			<k-box theme="text">
				<k-text :html="current.content" />
			</k-box>
		</template>

		<template v-else>
			<k-section v-if="topLevelArticles.length > 0">
				<k-collection :items="topLevelArticles.map(articleItem)" layout="cardlets" />
			</k-section>

			<k-section v-for="category in categories" :key="category.slug" :headline="category.title">
				<k-collection :items="categoryItems(category)" layout="cardlets" />
			</k-section>

			<k-section v-if="!articles || articles.length === 0">
				<k-empty icon="question">
					{{ $t("medienbaecker.help-view.empty") }}
				</k-empty>
			</k-section>
		</template>
	</k-panel-inside>
</template>

<script>
export default {
	props: {
		articles: {
			type: Array,
			default: () => []
		},
		current: {
			type: Object,
			default: null
		}
	},
	computed: {
		topLevelArticles() {
			return this.articles.filter(item => !item.children);
		},
		categories() {
			return this.articles.filter(item => item.children);
		},
		flatArticles() {
			const flat = [];
			for (const item of this.articles) {
				if (item.children) {
					flat.push(...item.children);
				} else {
					flat.push(item);
				}
			}
			return flat;
		},
		currentIndex() {
			if (!this.current) return -1;
			return this.flatArticles.findIndex(a => a.slug === this.current.slug);
		},
		prev() {
			if (this.currentIndex <= 0) return false;
			const article = this.flatArticles[this.currentIndex - 1];
			return { link: '/help/' + article.slug };
		},
		next() {
			if (this.currentIndex < 0 || this.currentIndex >= this.flatArticles.length - 1) return false;
			const article = this.flatArticles[this.currentIndex + 1];
			return { link: '/help/' + article.slug };
		}
	},
	methods: {
		articleItem(article) {
			return {
				text: article.title,
				link: '/help/' + article.slug,
				image: {
					icon: article.icon,
					color: article.color,
					back: article.back
				}
			};
		},
		categoryItems(category) {
			return category.children.map(child => ({
				text: child.title,
				link: '/help/' + child.slug,
				image: {
					icon: child.icon,
					color: child.color,
					back: child.back
				}
			}));
		}
	}
};
</script>

<style>
.k-help-view .k-text > p {
	max-width: 80ch;
}
</style>
