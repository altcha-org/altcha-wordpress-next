/**
 * Copyright (c) 2025 BAU Software s.r.o., Czechia. All rights reserved.
 *
 * This file is part of the Software licensed under the
 * END-USER LICENSE AGREEMENT (EULA)
 *
 * License Summary:
 * - Source is available for review, testing, debugging, and evaluation.
 * - Distribution of the Software or source code is prohibited.
 * - Modifications are allowed only for internal testing/debugging,
 *   not for production or deployment.
 *
 * The full license text can be found in the LICENSE file
 * distributed with this source code.
 *
 * Unauthorized distribution, modification, or production use of
 * this Software is strictly prohibited.
 */

(() => {
  document.addEventListener("DOMContentLoaded", () => {
    requestAnimationFrame(() => {
      [...document.querySelectorAll("altcha-widget")].forEach((el) => {
        const altcha = el.querySelector(".altcha");
        const checkbox = el.querySelector('input[type="checkbox"]');
        const form = el.closest("form");
        if (form && checkbox && altcha?.getAttribute("data-state") !== "code") {
          form.addEventListener(
            "submit",
            (ev) => {
              if (
                altcha?.getAttribute("data-state") !== "code" &&
                !checkbox.reportValidity()
              ) {
                ev.preventDefault();
                ev.stopPropagation();
              }
            },
            true
          );
        }
      });

      // Removes duplicate widgets when manipulated with JS such as elementor popups
      const observer = new MutationObserver(() => {
        [...document.querySelectorAll("altcha-widget")].forEach((el) => {
          const altchas = [...el.querySelectorAll(".altcha")];
          if (altchas.length > 1) {
            altchas.slice(0, -1).forEach((altcha) => altcha.remove());
          }
        });
      });
      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });
    });
  });
})();
