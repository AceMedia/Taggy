(function ($) {
    $(document).ready(function () {
        console.log("Document is ready.");
        
        // Function to check if the tag input field exists and is ready
        function checkForTagInputField() {
            var tagInput = $('#components-form-token-input-0'); // Use the correct selector for the tag input field
            console.log("Checking for tag input field:", tagInput);

            if (tagInput.length > 0) {
                console.log("Tag input field found. Preparing to create results list.");

                // Create the results container (if it doesn't exist) below the tag input field
                if ($('#taggy-results').length === 0) {
                    tagInput.after('<ul id="taggy-results" class="custom-tag-suggestions"></ul>');
                }

                var resultsList = $('#taggy-results');
                var timeoutId;
                var delay = 500; // Delay in milliseconds

                // Monitor the input field for typing
                tagInput.on('input', function () {
                    var query = $(this).val().trim(); // Trim any leading/trailing whitespace
                    query = query.replace(/\s+/g, '+'); // Replace spaces with `+`
                    console.log("Input event detected. Current input value:", query);

                    // Clear previous suggestions if the input is too short
                    if (query.length < 3) {
                        resultsList.empty(); // Clear the list
                        console.log("Input length is less than 3 characters. Clearing suggestions.");
                        return;
                    }

                    // Debounce the API call
                    if (timeoutId) {
                        clearTimeout(timeoutId);
                        console.log("Cleared previous timeout.");
                    }

                    timeoutId = setTimeout(function () {
                        console.log("Making AJAX request to OMDb API with query:", query);

                        $.ajax({
                            url: 'https://www.omdbapi.com/?s=' + encodeURIComponent(query) + '&apikey=' + taggy_api_key.apiKey,
                            success: function (data) {
                                console.log("Received response from OMDb API:", data);

                                resultsList.empty(); // Clear the list before adding new suggestions

                                if (data.Search) {
                                    data.Search.forEach(function (result) {
                                        var listItem = $('<li>' + result.Title + '</li>');

                                        // Insert the clicked tag suggestion into the input field
                                        listItem.on('click', function () {
                                            console.log("Suggestion clicked:", result.Title);
                                            tagInput.val(result.Title + ',');
                                            tagInput.focus();
                                            
                                            // Simulate typing a space and then a backspace
                                            setTimeout(function () {
                                                tagInput.val(tagInput.val() + ' ');  // Add a space
                                                
                                                setTimeout(function () {
                                                    tagInput.val(tagInput.val().slice(0, -1));  // Simulate backspace by removing the space
                                                    tagInput.trigger('input');  // Trigger input event to simulate typing behavior
                                                }, 10);  // Short delay before the backspace
                                            }, 10);  // Short delay before adding the space

                                            resultsList.empty(); // Clear the suggestions after selection
                                        });

                                        resultsList.append(listItem);
                                    });
                                } else {
                                    console.log("No results found.");
                                    resultsList.append('<li>No suggestions found.</li>');
                                }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.log("AJAX request failed:", textStatus, errorThrown);
                                resultsList.append('<li>Error fetching suggestions.</li>');
                            }
                        });
                    }, delay);
                });
            } else {
                console.log("Tag input field not found yet. Retrying...");
                setTimeout(checkForTagInputField, 500); // Try again after 500ms
            }
        }

        // Start checking for the tag input field
        checkForTagInputField();
    });
})(jQuery);
