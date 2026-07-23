import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.smartrestSearchableSelect = function smartrestSearchableSelect(config) {
    return {
        endpoint: config.endpoint,
        placeholder: config.placeholder,
        staticOption: config.staticOption,
        selected: config.selected,
        query: config.selected?.label ?? config.staticOption?.label ?? '',
        options: [],
        loading: false,
        open: false,
        highlightedIndex: 0,
        hasMore: false,
        nextPage: null,

        init() {
            const hiddenValue = this.$refs.hidden.value;

            if (
                this.selected === null
                && this.staticOption !== null
                && this.staticOption !== undefined
                && hiddenValue === this.staticOption.id.toString()
            ) {
                this.selected = this.staticOption;
                this.query = this.staticOption.label;
            }

            this.options = this.mergeOptions(config.initialOptions ?? []);
        },

        openList() {
            this.open = true;

            if (this.options.length === 0) {
                this.fetchOptions(1, false);
            }
        },

        close() {
            this.open = false;
            this.highlightedIndex = 0;

            if (this.selected !== null) {
                this.query = this.selected.label;

                return;
            }

            this.query = '';
        },

        toggle() {
            if (this.open) {
                this.close();

                return;
            }

            this.openList();
            this.$nextTick(() => this.$refs.search.focus());
        },

        search() {
            this.open = true;
            this.fetchOptions(1, false);
        },

        loadMore() {
            if (this.nextPage === null) {
                return;
            }

            this.fetchOptions(this.nextPage, true);
        },

        choose(option) {
            this.selected = option;
            this.query = option.label;
            this.close();
            this.syncHidden();
        },

        clear() {
            this.selected = this.staticOption ?? null;
            this.query = this.selected?.label ?? '';
            this.close();
            this.syncHidden();
        },

        chooseHighlighted() {
            if (!this.open) {
                this.openList();

                return;
            }

            const option = this.options[this.highlightedIndex];

            if (option !== undefined) {
                this.choose(option);
            }
        },

        highlightNext() {
            this.openList();

            if (this.options.length === 0) {
                return;
            }

            this.highlightedIndex = (this.highlightedIndex + 1) % this.options.length;
        },

        highlightPrevious() {
            this.openList();

            if (this.options.length === 0) {
                return;
            }

            this.highlightedIndex = (this.highlightedIndex - 1 + this.options.length) % this.options.length;
        },

        activeDescendantId(fieldId) {
            if (!this.open || this.options[this.highlightedIndex] === undefined) {
                return null;
            }

            return `${fieldId}_option_${this.highlightedIndex}`;
        },

        fetchOptions(page, append) {
            this.loading = true;

            const url = new URL(this.endpoint, window.location.origin);
            url.searchParams.set('page', page.toString());
            url.searchParams.set('q', this.query);

            fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(response))
                .then((payload) => {
                    const remoteOptions = Array.isArray(payload.options) ? payload.options : [];
                    const nextOptions = append ? this.options.concat(remoteOptions) : remoteOptions;

                    this.options = this.mergeOptions(nextOptions);
                    this.hasMore = Boolean(payload.has_more);
                    this.nextPage = payload.next_page ?? null;
                    this.highlightedIndex = 0;
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        mergeOptions(options) {
            const merged = [];
            const seen = new Set();

            for (const option of [this.staticOption, this.selected, ...options]) {
                if (option === null || option === undefined) {
                    continue;
                }

                const id = Number(option.id);

                if (seen.has(id)) {
                    continue;
                }

                seen.add(id);
                merged.push({ id, label: option.label });
            }

            return merged;
        },

        syncHidden() {
            this.$nextTick(() => {
                const value = this.selected?.id ?? '';
                this.$refs.hidden.value = value.toString();
                this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },
    };
};

Livewire.start();
