function generateSummary() {
  const contentTextarea = document.querySelector(".wp-editor-area");
  const summaryTextarea = document.querySelector("#acf-field_5f21a06d22c58");
  const generateSummaryButton = document.querySelector(
    ".generate-summary-button",
  );
  const contentRegio = document.querySelector("#regiochecklist");

  if (
    !contentTextarea ||
    !summaryTextarea ||
    !generateSummaryButton ||
    !contentRegio
  ) {
    return;
  }

  const content = contentTextarea.value.replace(/<[^>]*>|\\[[^\\]]*]/g, "");
  console.debug("Used content:", content); // debug
  const selectedRegios = Array.from(
    contentRegio.querySelectorAll('input[type="checkbox"]:checked'),
  ).map((checkbox) => {
    const labelText = checkbox.parentNode.cloneNode(true);
    Array.from(labelText.children).forEach((child) => child.remove());
    return labelText.textContent.trim().toUpperCase();
  });

  // Disable the "Generate summary" button, show a spinner, and update the summary textarea
  generateSummaryButton.classList.add("disabled");
  summaryTextarea.disabled = true;
  summaryTextarea.value =
    "\u2728 AI aan het raadplegen. Dit duurt een paar seconden...";
  const spinner = document.createElement("span");
  spinner.className = "spinner is-active";
  generateSummaryButton.parentNode.insertBefore(
    spinner,
    generateSummaryButton.nextSibling,
  );

  // Call the PHP function to generate the summary using the OpenAI API
  jQuery
    .post(ajaxurl, {
      action: "generate_summary",
      content: content,
      _ajax_nonce: ttvgpt_ajax_vars.nonce,
    })
    .done(function (summary) {
      // Add selected regio labels to the summary
      summary = `${selectedRegios.join(" / ")} - ${summary}`;

      // Clear the placeholder and simulate typing effect by adding each character with random delays
      summaryTextarea.value = "";
      let i = 0;

      function typeCharacter() {
        if (i < summary.length) {
          summaryTextarea.value += summary.charAt(i);
          i++;
          let delay = 0 + Math.random() * 1; // Randomize typing speed

          if (summary.charAt(i - 1) === " ") {
            // If the last character was a space, add extra delay
            delay += 1; // Adjust this value to change the extra delay between words
          }

          setTimeout(typeCharacter, delay);
        } else {
          // Re-enable the "Generate summary" button, remove the spinner, and re-enable the summary textarea
          generateSummaryButton.classList.remove("disabled");
          summaryTextarea.disabled = false;
          spinner.remove();
        }
      }

      typeCharacter();
    });
}
