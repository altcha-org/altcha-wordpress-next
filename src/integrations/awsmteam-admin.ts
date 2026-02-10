class AwsmTeamIntegrationAdmin {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {

            const container = document.getElementById("member_details")?.querySelector(".inside");
            const table = document.getElementById("altcha-obfuscation-settings");
            if (!container || !table) return false;
            // If the table is already inside the intended container, do nothing.
            if (container.contains(table)) return true;
            const lastChild = container.lastElementChild;
            if (lastChild) {
                container.insertBefore(table, lastChild);
            } else {
                container.appendChild(table);
            }

            const select = table.querySelector("#altcha-obfuscation-toggle");
            const input = table.querySelector("#altcha-obfuscation-label");
            select?.addEventListener("change", (event) => {
                const isChecked = (event.target as HTMLSelectElement).value === "enabled";
                if (isChecked) {
                    input?.removeAttribute("readonly");
                } else {
                    input?.setAttribute("readonly", "true");
                }
            });

        });
    }
}

new AwsmTeamIntegrationAdmin();