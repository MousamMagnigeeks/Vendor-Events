jQuery(function($){
    $('#ven_fetch_title').on('click', function(e){
        e.preventDefault();
        var url = $('#ven_event_url').val();
        if (!url) {
            alert('Please enter a URL first.');
            return;
        }
        var data = {
            action: 'ven_fetch_title',
            url: url,
            nonce: ven_ajax.nonce
        };
        $('#ven_fetch_title').prop('disabled', true).text('Fetching...');
        $.post(ven_ajax.ajax_url, data, function(resp){
            $('#ven_fetch_title').prop('disabled', false).text('Fetch Title');
            if (!resp || !resp.success) {
                alert(resp && resp.data ? resp.data : 'Failed to fetch title');
                return;
            }
            $('#ven_event_title').val(resp.data.title);
            validateInput(true);
        }, 'json').fail(function(){
            $('#ven_fetch_title').prop('disabled', false).text('Fetch Title');
            alert('Request failed.');
        });
    });

    $("#ven_start_date, #ven_end_date").datepicker({
        dateFormat: "dd-mm-yy",
        changeMonth: true,
        changeYear: true,
        showAnim: "slideDown"
    });

    const $input = $('#ven_event_title');
    const $form = $input.closest('form');
    const $submit = $form.find('button[type="submit"], input[type="submit"]');

    function validateInput(showError = true) {
        const value = $input.val().trim();
        const valid = /^[A-Za-z\s]+$/.test(value);

        if (!valid && value !== '') {
            $input.css('border', '2px solid red');
            if (showError && !$input.next('.error-message').length) {
                $input.after('<span class="error-message" style="color:red; font-size:12px;">Only letters and spaces are allowed.</span>');
            }
            // $submit.prop('disabled', true).css('opacity', '0.6');
        } else {
            $input.css('border', '');
            $input.next('.error-message').remove();
            // Only enable submit if field is not empty and valid
            // $submit.prop('disabled', value === '' ? true : false).css('opacity', value === '' ? '0.6' : '1');
        }

        return valid;
    }

    // 🔹 Validate only when user types or leaves the field
    $input.on('input blur', function() {
        validateInput(true);
    });

    // 🔹 Prevent submission if invalid
    $form.on('submit', function(e) {
        if (!validateInput(true)) {
            e.preventDefault();
            $input.focus();
        }
    });

    // 🔹 Initial state: just disable submit if field empty (no error shown)
    // if ($input.val().trim() === '') {
        // $submit.prop('disabled', true).css('opacity', '0.6');
    // }

    $(document).on('change', '.ven-status-select-frontend', function() {
        const $select = $(this);
        const eventId = $select.data('event-id');
        const newStatus = $select.val();

        // Disable temporarily
        $select.prop('disabled', true);

        $.ajax({
            url: ven_ajax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ven_update_event_status',
                event_id: eventId,
                status: newStatus,
                nonce: ven_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Status updated to: ' + response.data.new_status_label);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('AJAX error while updating status.');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    });

    const usStates = {
        "AL": "Alabama", "AK": "Alaska", "AZ": "Arizona", "AR": "Arkansas", "CA": "California",
        "CO": "Colorado", "CT": "Connecticut", "DE": "Delaware", "FL": "Florida", "GA": "Georgia",
        "HI": "Hawaii", "ID": "Idaho", "IL": "Illinois", "IN": "Indiana", "IA": "Iowa", "KS": "Kansas",
        "KY": "Kentucky", "LA": "Louisiana", "ME": "Maine", "MD": "Maryland", "MA": "Massachusetts",
        "MI": "Michigan", "MN": "Minnesota", "MS": "Mississippi", "MO": "Missouri", "MT": "Montana",
        "NE": "Nebraska", "NV": "Nevada", "NH": "New Hampshire", "NJ": "New Jersey", "NM": "New Mexico",
        "NY": "New York", "NC": "North Carolina", "ND": "North Dakota", "OH": "Ohio", "OK": "Oklahoma",
        "OR": "Oregon", "PA": "Pennsylvania", "RI": "Rhode Island", "SC": "South Carolina",
        "SD": "South Dakota", "TN": "Tennessee", "TX": "Texas", "UT": "Utah", "VT": "Vermont",
        "VA": "Virginia", "WA": "Washington", "WV": "West Virginia", "WI": "Wisconsin", "WY": "Wyoming"
    };

    const caProvinces = {
        "AB": "Alberta", "BC": "British Columbia", "MB": "Manitoba", "NB": "New Brunswick",
        "NL": "Newfoundland and Labrador", "NS": "Nova Scotia", "NT": "Northwest Territories",
        "NU": "Nunavut", "ON": "Ontario", "PE": "Prince Edward Island", "QC": "Quebec",
        "SK": "Saskatchewan", "YT": "Yukon"
    };

    const selectedCountry = window.venLocationData?.selectedCountry || "";
    const selectedState = window.venLocationData?.selectedState || "";

    function populateStates(country, selectedState) {
        const $stateSelect = $('#ven_state_province');
        $stateSelect.empty().append('<option value="">Select State/Province</option>');

        let regions = {};
        if (country === 'US') {
            regions = usStates;
        } else if (country === 'Canada') {
            regions = caProvinces;
        }

        $.each(regions, function (code, name) {
            const selected = (code === selectedState || name === selectedState) ? 'selected' : '';
            $stateSelect.append(`<option value="${code}" ${selected}>${name}</option>`);
        });
    }

    // ✅ Populate only when user changes the country
    $('#ven_country').on('change', function () {
        const country = $(this).val();
        populateStates(country, '');
    });

    // ✅ Populate if editing an existing event
    if (selectedCountry) {
        populateStates(selectedCountry, selectedState);
    }
});
