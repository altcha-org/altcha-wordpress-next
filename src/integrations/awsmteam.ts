interface SocialLink {
    url?: string;
    icon?: string;
}

class AwsmTeamIntegration {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('altcha-widget[data-social]').forEach(widget => {
                widget.addEventListener('statechange', (event: Event | CustomEvent) => {
                    const ce = event as CustomEvent | undefined;
                    if (!ce || !ce.detail || (ce.detail as any).state !== 'verified') return;
                    const links = this.nodeToLinks(widget);
                    if (!links) return;
                    const fragment = this.generateSocialLinks(links as SocialLink[]);
                    if (fragment) widget.appendChild(fragment);
                });
            });
        });
    }

    nodeToLinks(widget: Element): SocialLink[] | null {
        let jsonText = '';
        Array.from(widget.childNodes).forEach(node => {
            if (node.nodeType === Node.TEXT_NODE) {
                jsonText += String(node.nodeValue);
                node.remove();
            }
        });
        if (!jsonText) return null;
        try {
            const decoded = this.htmlDecode(jsonText);
            const links = JSON.parse(decoded);
            if (!Array.isArray(links)) return null;
            return links;
        } catch (e) {
            return null;
        }
    }

    generateSocialLinks(links: SocialLink[]): HTMLElement | null {
        if (!Array.isArray(links) || links.length === 0) return null;
        const container = document.createElement('div');
        container.className = 'awsm-social-icons';
        links.forEach(link => {
            if (!link || typeof link !== 'object') return;
            const url = typeof link.url === 'string' ? link.url.trim() : '';
            if (!url || !this.isSafeUrl(url)) return;
            const iconClass = typeof link.icon === 'string' && link.icon.trim() ? link.icon.trim() : 'fa fa-link';
            const span = document.createElement('span');
            const a = document.createElement('a');
            a.className = 'altcha-obfuscation-button';
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            const i = document.createElement('i');
            i.className = iconClass;
            i.setAttribute('aria-hidden', 'true');
            a.appendChild(i);
            span.appendChild(a);
            container.appendChild(span);
        });
        return container;
    }

    isSafeUrl(s: unknown): boolean {
        if (typeof s !== 'string') return false;
        const url = s.trim();
        const lower = url.toLowerCase();
        if (lower.startsWith('javascript:') || lower.startsWith('data:') || lower.startsWith('vbscript:')) return false;
        const protoMatch = /^[a-zA-Z][a-zA-Z\d+\-.]*:/.exec(url);
        if (!protoMatch) return true; // relative URL
        const proto = protoMatch[0].toLowerCase();
        return proto.startsWith('http:') || proto.startsWith('https:') || proto.startsWith('mailto:') || proto.startsWith('tel:');
    }

    htmlDecode(str: unknown): string {
        if (typeof str !== 'string') return String(str);
        const div = document.createElement('div');
        div.innerHTML = str;
        return div.textContent || div.innerText || '';
    }
}

new AwsmTeamIntegration();