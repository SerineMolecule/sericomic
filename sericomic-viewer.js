'use strict';
// @ts-check

/**
 * @typedef {Object} ComicChapter
 * @property {string} title
 * @property {{ file: string; width: number; height: number }[]} pages
 * @property {string} dir
 * @property {number} firstPage
 */

/**
 * @typedef {Object} ComicData
 * @property {ComicChapter[]} chapters
 * @property {number} firstChapter
 * @property {number} lastUpdated
 */

const SERICOMIC_URL_PREFIX = '/comic/';
const SERICOMIC_UPLOADS_URL = '/wp-content/uploads/sericomic/';

class Sericomic {
	/** @type {HTMLDivElement} */
	div = null;
	/** @type {ComicData} */
	data;
	singlePage = true;
	chapter = 0;
	page = 0;
	/**
	 * @param {HTMLDivElement} div
	 * @param {ComicData} data
	 */
	constructor(div, data) {
		this.div = div;
		this.data = data;

		this.div.addEventListener('change', this.onChange);
		this.div.addEventListener('click', this.onClick);
		window.addEventListener('keydown', this.onKeyDown);
		window.addEventListener('popstate', this.onPopState);

		this.handleNavigation();
	}
	onPopState = (ev) => {
		this.handleNavigation();
	};
	onKeyDown = (ev) => {
		if (ev.key === 'a' || ev.key === 'ArrowLeft') {
			this.goTo(this.prevComic());
		} else if (ev.key === 'd' || ev.key === 'ArrowRight') {
			this.goTo(this.nextComic());
		} else if (ev.key === 'w' || ev.key === 's') {
			const imgScroll = this.div.querySelector('.sericomic-image').getBoundingClientRect();
			const imgTop = window.scrollY + imgScroll.top;
			const imgBottom = window.scrollY + imgScroll.bottom - window.innerHeight;
			const scrollDist = Math.trunc(window.innerHeight / 2);
			if (ev.key === 'w') {
				if (window.scrollY - 8 > imgTop && window.scrollY - scrollDist < imgTop) {
					window.scrollTo({ top: imgTop });
				} else {
					window.scrollBy({ top: -scrollDist });
				}
			} else if (ev.key === 's') {
				if (window.scrollY + 8 < imgTop && window.scrollY + scrollDist > imgTop) {
					window.scrollTo({ top: imgTop });
				} else if (window.scrollY + 8 < imgBottom && window.scrollY + scrollDist > imgBottom) {
					window.scrollTo({ top: imgBottom });
				} else {
					window.scrollBy({ top: scrollDist });
				}
			}
		}
	};
	onChange = (ev) => {
		const select = ev.target.closest('select');
		if (select) {
			if (select.classList.contains('sericomic-chapters') || select.classList.contains('sericomic-pages')) {
				this.goTo(select.value);
			}
		}
	};
	onClick = (ev) => {
		// const button = ev.target.closest('button');
		// if (button) this.goTo(button.dataset.page);

		const button = ev.target.closest('a');
		if (button && button.getAttribute('href')?.startsWith(SERICOMIC_URL_PREFIX)) {
			this.goTo(button.getAttribute('href').slice(SERICOMIC_URL_PREFIX.length));
			ev.preventDefault();
			ev.stopImmediatePropagation();
		}
	};
	firstComic() {
		return this.getPageid(0, 0);
	}
	lastComic() {
		const lastChapter = this.data.chapters.length - 1;
		const lastPage = this.data.chapters[lastChapter].pages.length - 1;
		return this.getPageid(lastChapter, lastPage);
	}
	prevComic() {
		let newChapter = this.chapter;
		let newPage = this.page;
		newPage--;
		if (!this.data.chapters[newChapter].pages[newPage]) {
			newChapter--;
			if (!this.data.chapters[newChapter]) return null;
			newPage = this.data.chapters[newChapter].pages.length - 1;
		}

		return this.getPageid(newChapter, newPage);
	}
	nextComic() {
		let newChapter = this.chapter;
		let newPage = this.page;
		newPage++;
		if (!this.data.chapters[newChapter].pages[newPage]) {
			newChapter++;
			if (!this.data.chapters[newChapter]) return null;
			newPage = 0;
		}

		return this.getPageid(newChapter, newPage);
	}
	handleNavigation(pageid = null) {
		if (pageid === null) {
			if (location.pathname.startsWith(SERICOMIC_URL_PREFIX)) {
				pageid = location.pathname.slice(SERICOMIC_URL_PREFIX.length);
			}
		}
		if (pageid === '') {
			this.chapter = 0;
			this.page = 0;
		} else {
			const parsed = this.parsePageid(pageid);
			if (parsed) {
				[this.chapter, this.page] = parsed;
			} else {
				this.chapter = -1;
				this.page = 0;
			}
		}
		if (pageid !== this.getPageid()) {
			history.replaceState({}, '', `${SERICOMIC_URL_PREFIX}${this.getPageid()}`);
		}
		this.render();
	}
	parsePageid(pageid) {
		if (!pageid) return null;
		const match = /^ch([0-9]+)(?:\/p([0-9]+))?\/?$/.exec(pageid);
		if (!match) return null;
		const [, chapterStr, pageStr] = match;
		let chapter = parseInt(chapterStr) - this.data.firstChapter;
		if (chapter === -this.data.firstChapter) chapter = 0;
		const chapterData = this.data.chapters[chapter];
		let page = parseInt(pageStr || '0') - chapterData?.firstPage;
		if (page === -chapterData?.firstPage) page = 0;
		if (isNaN(chapter) || isNaN(page)) return null;
		return [chapter, page];
	}
	getPageid(chapter = this.chapter, page = this.page) {
		const pageNum = page + this.data.chapters[chapter]?.firstPage;
		return `ch${chapter + this.data.firstChapter}/${pageNum ? `p${pageNum}` : ''}`;
	}
	goTo(pageid) {
		if (!pageid) return;
		history.pushState({}, '', `${SERICOMIC_URL_PREFIX}${pageid}`);
		this.handleNavigation(pageid);
	}
	img(pageid = this.getPageid()) {
		if (!pageid) return '';
		const [chapter, page] = this.parsePageid(pageid);
		const pageData = this.data.chapters[chapter].pages[page];
		const url = this.imgUrl(pageid);
		return `<img class="sericomic-image" src="${url}" width="${pageData.width}" height="${pageData.height}" alt="comic page" />`;
	}
	imgUrl(pageid = this.getPageid()) {
		if (!pageid) return '';
		const [chapter, page] = this.parsePageid(pageid);
		const pageData = this.data.chapters[chapter].pages[page];
		const cachebuster = `?v=${this.data.lastUpdated}`;
		const url = `${SERICOMIC_UPLOADS_URL}chapters/${this.data.chapters[chapter].dir}/${pageData.file}${cachebuster}`;
		return url;
	}
	preloadImg(url) {
		if (!url) return;
		const img = new Image();
		img.src = url;
	}
	escapeHTML(str) {
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&apos;')
			.replace(/\//g, '&#x2f;')
			.replace(/\n/g, '<br />');
	}
	button(text, pageid, ariaLabel = '') {
		// if (!pageid) {
		// 	return `<button disabled class="sericomic-disabledbutton">${text}</button>`;
		// }
		// return `<button class="sericomic-button" data-page="${pageid}">${text}</button>`;
		const ariaLabelAttr = ariaLabel ? `aria-label="${ariaLabel}"` : '';
		if (!pageid || pageid === this.getPageid()) {
			return `<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-accent-4-background-color wp-element-button sericomic-disabledbutton" href="${SERICOMIC_URL_PREFIX}${this.getPageid()}"${ariaLabelAttr}>${text}</a></div>`;
		}
		return `<div class="wp-block-button"><a class="wp-block-button__link wp-element-button sericomic-button" href="${SERICOMIC_URL_PREFIX}${pageid}"${ariaLabelAttr}>${text}</a></div>`;
	}
	renderChapters() {
		let html = '';
		html += '<select class="sericomic-chapters" value="' + this.getPageid(this.chapter, 0) + '">';
		for (let i = 0; i < this.data.chapters.length; i++) {
			const chapter = this.data.chapters[i];
			const selected = (i === this.chapter) ? ' selected' : '';
			const pageid = this.getPageid(i, 0);
			html += `<option value="${pageid}"${selected}>${this.escapeHTML(chapter.title)}</option>`;
		}
		html += '</select>';
		return html;
	}
	renderPages() {
		let html = '';
		html += '<select class="sericomic-pages" value="' + this.getPageid() + '">';
		const chapter = this.data.chapters[this.chapter];
		for (let i = 0; i < chapter.pages.length; i++) {
			const selected = (i === this.page) ? ' selected' : '';
			const pageid = this.getPageid(this.chapter, i);
			const pageNum = i + chapter.firstPage;
			const pageName = pageNum === 0 ? 'Title' : `Page ${pageNum}`;
			html += `<option value="${pageid}"${selected}>${pageName}</option>`;
		}
		html += '</select>';
		return html;
	}
	renderButtons() {
		let html = '<div class="wp-block-buttons is-content-justification-center is-layout-flex wp-container-core-buttons-is-layout-1 wp-block-buttons-is-layout-flex">'
		// html += this.button('<i aria-hidden="true" class="fas fa-chevron-left"></i> Previous Page', this.prevComic());
		// html += this.button('Next Page <i aria-hidden="true" class="fas fa-chevron-right"></i>', this.nextComic());
		html += this.button('&lt;&lt;', this.firstComic(), 'First');
		html += this.button('&lt; Prev', this.prevComic());
		html += this.button('Next &gt;', this.nextComic());
		html += this.button('&gt;&gt;', this.lastComic(), 'Last');
		html += '</div>';
		return html;
	}
	render() {
		if (this.chapter < 0) {
			this.div.innerHTML = `<h1>Error</h1><p><code>${this.escapeHTML(location.pathname)}</code> is not a valid page.</p>`;
		}
		let html = '';
		const title = this.data.chapters[this.chapter].title;
		// const titleElem = document.querySelector('.page-title');
		/** @type {HTMLElement} */
		const titleElem = document.querySelector('.wp-block-post-title');
		let titleChanged = false;
		if (titleElem) {
			titleChanged = (titleElem.innerText !== title);
			if (titleChanged) {
				titleElem.innerText = title;
			}
		}

		html += '<div class="sericomic-buttonbar">';
		html += '<p>' + this.renderChapters() + ' ' + this.renderPages() + '</p>';
		html += '</div>';
		html += this.renderButtons();
		html += this.img();
		html += this.renderButtons();

		this.preloadImg(this.imgUrl(this.nextComic()));

		const oldImg = this.div.querySelector('.sericomic-image');
		if (oldImg) {
			const imgScroll = this.div.querySelector('.sericomic-image').getBoundingClientRect();
			const scrollTop = window.scrollY + imgScroll.top;
	
			if (window.scrollY > scrollTop) {
				window.scrollTo({ left: 0, top: scrollTop, behavior: 'instant' });
			}
		}
		this.div.innerHTML = html;
	}
}
