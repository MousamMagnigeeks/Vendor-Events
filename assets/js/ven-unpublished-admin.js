jQuery( function( $ ) {
    // add our custom post status option if not already present
    // if ( $( '#post_status option[value="unpublished"]' ).length === 0 ) {
    //     $( '#post_status' ).append( '<option value="unpublished">Unpublished</option>' );
    // }

    // if current post is already unpublished, set UI
    if ( typeof venUnpublish !== 'undefined' ) {
        try {
            var currentPostStatus = $( '#post_status' ).val();
            if ( currentPostStatus === 'unpublished' || $( '#post-status-display' ).text().trim().toLowerCase() === 'unpublished' ) {
                $( '#post-status-display' ).text( 'Unpublished' );
                $( '#post_status' ).val( 'unpublished' );
            }
        } catch (e) {}
    }

    // show modal when status changed to unpublished
    $( document ).on( 'change', '#post_status', function() {
        var newStatus = $( this ).val();
        if ( newStatus === 'unpublished' ) {
            // build modal
            var modal = '\
                <div id="ven-reason-modal" class="ven-modal">\
                    <div class="ven-modal-content">\
                        <h3 style="margin:0 0 8px 0">Reason for Unpublishing</h3>\
                        <textarea id="ven-reason-text" rows="5" placeholder="Enter reason for unpublishing this event..."></textarea>\
                        <div class="ven-modal-actions">\
                            <button type="button" id="ven-cancel-reason" class="button">Cancel</button>\
                            <button type="button" id="ven-send-reason" class="button button-primary" data-post-id="' + (venUnpublish.post_id || $( '#post_ID' ).val()) + '">Send & Save</button>\
                        </div>\
                    </div>\
                </div>';

            // append and focus
            if ( $( '#ven-reason-modal' ).length ) {
                $( '#ven-reason-modal' ).remove();
            }
            $( 'body' ).append( modal );
            $( '#ven-reason-text' ).focus();
        }
    });

    // cancel modal: revert dropdown to previous status
    $( document ).on( 'click', '#ven-cancel-reason', function( e ) {
        e.preventDefault();
        // try to reset status to previous value; default to 'publish'
        var prev = $( '#post_status' ).data( 'previous-status' ) || 'publish';
        $( '#post_status' ).val( prev ).trigger( 'change' );
        $( '#ven-reason-modal' ).remove();
    });

    // store previous status when user focuses the select (so cancel can revert)
    $( document ).on( 'focus', '#post_status', function() {
        $( this ).data( 'previous-status', $( this ).val() );
    });

    // send reason via ajax
    $( document ).on( 'click', '#ven-send-reason', function( e ) {
        e.preventDefault();
        var $btn = $( this );
        var postId = parseInt( $btn.data( 'post-id' ), 10 ) || parseInt( $( '#post_ID' ).val(), 10 );
        var reason = $( '#ven-reason-text' ).val().trim();

        if ( ! postId || reason.length === 0 ) {
            alert( 'Please provide a reason.' );
            return;
        }

        $btn.prop( 'disabled', true ).text( 'Sending...' );

        $.post( venUnpublish.ajax_url, {
            action: 'ven_send_unpublish_reason',
            nonce: venUnpublish.nonce,
            post_id: postId,
            reason: reason
        }, function( res ) {
            if ( res && res.success ) {
                // close modal and optionally show WP notice
                $( '#ven-reason-modal' ).remove();
                // update the post status display text
                $( '#post-status-display' ).text( 'Unpublished' );
                // optionally show a small admin notice
                if ( res.data && res.data.message ) {
                    alert( res.data.message );
                    location.reload();
                }
            } else {
                alert( (res && res.data && res.data.message) ? res.data.message : 'Error sending reason.' );
            }
        }, 'json' ).always( function() {
            $btn.prop( 'disabled', false ).text( 'Send & Save' );
        } );
    } );
} );

jQuery(function ($) {
    // Define state/province data
    const usStates = {
        'AL': 'Alabama', 'AK': 'Alaska', 'AZ': 'Arizona', 'AR': 'Arkansas', 'CA': 'California',
        'CO': 'Colorado', 'CT': 'Connecticut', 'DE': 'Delaware', 'FL': 'Florida', 'GA': 'Georgia',
        'HI': 'Hawaii', 'ID': 'Idaho', 'IL': 'Illinois', 'IN': 'Indiana', 'IA': 'Iowa',
        'KS': 'Kansas', 'KY': 'Kentucky', 'LA': 'Louisiana', 'ME': 'Maine', 'MD': 'Maryland',
        'MA': 'Massachusetts', 'MI': 'Michigan', 'MN': 'Minnesota', 'MS': 'Mississippi',
        'MO': 'Missouri', 'MT': 'Montana', 'NE': 'Nebraska', 'NV': 'Nevada', 'NH': 'New Hampshire',
        'NJ': 'New Jersey', 'NM': 'New Mexico', 'NY': 'New York', 'NC': 'North Carolina',
        'ND': 'North Dakota', 'OH': 'Ohio', 'OK': 'Oklahoma', 'OR': 'Oregon', 'PA': 'Pennsylvania',
        'RI': 'Rhode Island', 'SC': 'South Carolina', 'SD': 'South Dakota', 'TN': 'Tennessee',
        'TX': 'Texas', 'UT': 'Utah', 'VT': 'Vermont', 'VA': 'Virginia', 'WA': 'Washington',
        'WV': 'West Virginia', 'WI': 'Wisconsin', 'WY': 'Wyoming'
    };

    const caProvinces = {
        'AB': 'Alberta', 'BC': 'British Columbia', 'MB': 'Manitoba', 'NB': 'New Brunswick',
        'NL': 'Newfoundland and Labrador', 'NS': 'Nova Scotia', 'NT': 'Northwest Territories',
        'NU': 'Nunavut', 'ON': 'Ontario', 'PE': 'Prince Edward Island', 'QC': 'Quebec',
        'SK': 'Saskatchewan', 'YT': 'Yukon'
    };

    // Get preselected country/state from PHP
    const selectedCountry = window.venLocationData?.selectedCountry || '';
    const selectedState = window.venLocationData?.selectedState || '';

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
            const isSelected = (code === selectedState || name === selectedState) ? 'selected' : '';
            $stateSelect.append(`<option value="${code}" ${isSelected}>${name}</option>`);
        });
    }

    // Populate when country changes
    $('#ven_country').on('change', function () {
        populateStates($(this).val(), '');
    });

    // Initialize on page load (edit screen)
    if (selectedCountry) {
        populateStates(selectedCountry, selectedState);
    }
});
