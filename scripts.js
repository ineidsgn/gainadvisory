+function ($) {

    $('#password_toggle').click(function(e) {
        e.preventDefault();
        $('#password_change').slideToggle('fast');
    });

    $( "form#gag_get_consultants" ).submit(function (event) {
        event.preventDefault();

        $(this).find("p.error-message").remove();
        $(this).find(".form-error").removeClass("form-error");

        errors = 0;

        $("select[name=industry]").each(function () {
            if ($(this).val().length === 0) {
                add_form_error_message(this,"<p class='error-message'>Industry field is mandatory.</p>");
                errors += 1;
            }
        });

        if (errors > 0) { return false; }

        var loader = $(this);
        var form_data = $(this).serialize();
        var adminurl = $(this).data("admin-url");
        console.log(form_data);
        load_ajax_users(form_data,adminurl,loader);
    });

    $( "form#gag_get_consultants_byname" ).submit(function (event) {
        event.preventDefault();

        $(this).find("p.error-message").remove();
        $(this).find(".form-error").removeClass("form-error");

        errors = 0;

        $("input[name=last_name]").each(function () {
            if ($(this).val().length === 0) {
                add_form_error_message(this,"<p class='error-message'>Last Name field is mandatory.</p>");
                errors += 1;
            }
        });

        if (errors > 0) { return false; }

        var loader = $(this);
        var form_data = $(this).serialize();
        var adminurl = $(this).data("admin-url");
        console.log(form_data);
        load_ajax_users(form_data,adminurl,loader);
    });

    $("select.gag-profile-select").each(function () {
        var $current_option = $(this).data("prevoption");
        $(this).find("option").each(function () {
            if ($(this).text() == $current_option) {
                $(this).attr("selected","selected");
            }
        })
    });

    /* Start and End date sync */
    var dates = $( "#startdate, #enddate" ).datepicker({
        changeMonth: true,
        changeYear: true,
        minDate: new Date(),
        onSelect: function( selectedDate ) {
            var option = this.id == "startdate" ? "minDate" : "maxDate",
                instance = $( this ).data( "datepicker" ),
                date = $.datepicker.parseDate(
                    instance.settings.dateFormat ||
                    $.datepicker._defaults.dateFormat,
                    selectedDate, instance.settings );
            dates.not( this ).datepicker( "option", option, date );
        }
    });

    $("#reactdate").datepicker({
        changeMonth: true,
        changeYear: true
    });

    $("#change_password_btn").click(function (e) {
        e.preventDefault();
        var minlength = $('#gag_change_password').data("minlength");
        $(".change-password-messages").html('');
        if ($('#pass_1').val() === '') {
            gag_form_validate($('#pass_1'));
        } else if ($('#pass_2').val() === '') {
            gag_form_validate($('#pass_2'));
        } else if ($('#pass_2').val() !== $('#pass_1').val()) {
            $(".change-password-messages").html('<p style="color: red;">Passwords do not match</p>');
        } else if ((minlength !== '') && ($('#pass_2').val().length < minlength)) {
            console.log(minlength);
            console.log($('#pass_2').val().length);

            $(".change-password-messages").html('<p style="color: red;">Password too short</p>');
        } else {
            $(".change-password-overlay").show();
            var ajaxurl = $('#gag_change_password').data("url");

            var pw_data = {
                'action': 'gag_change_password',
                'gag_pw_action': 'change_password',
                'new_password': $('#pass_2').val()
            };

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: pw_data,
                success: function (response) {
                    if (response === 'success') {
                        $(".change-password-overlay").hide();
                        $(".change-password-messages").html('<p style="color: green">Password Successfully Changed</p>');
                        $('.change-password-form').hide();
                    } else if (response === 'error') {
                        $(".change-password-overlay").hide();
                        $(".change-password-messages").html('<p style="color: red">Error Changing Password</p>');
                        $('.change-password-form').show();
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    //$content.html($.parseJSON(jqXHR.responseText) + ' :: ' + textStatus + ' :: ' + errorThrown);
                    console.log('Error');
                    console.log(jqXHR);
                }

            });

        }
    });


    //Hide reg fields depending on role
    $("#gag_registration_role").each(function () {
        var role = $(this).data("role");
        if (role === 'employer') {
            $(".pms-field-subscriptions").addClass("hidden");
            $("input[name=pms_register]").val("Register for Free");
        }
        if (role === 'consultant') {
            $(".pms-user-company-field").addClass("hidden");
            $("#member_company").val("none");
            $(".pms-user-jobtitle-field").addClass("hidden");
            $("#jobtitle").val("none");
        }

        $("#pms-submit-button-loading-placeholder-text").text($("input[name=pms_register]").val());

    });


    //Re-arrange, re-label and adjust html for form fields
    $("#pms_register-form").each(function () {
        //$(this).find("input[type=text]").attr("required","required");
        var jobtitle_field = $(this).find(".pms-user-jobtitle-field");
        var username_field = $(this).find(".pms-user-login-field");
        var email_field = $(this).find(".pms-user-email-field");

        $(jobtitle_field).remove().insertBefore(".pms-pass1-field");
        $(username_field).remove().insertBefore(".pms-pass1-field");
        $(email_field).remove().insertBefore(".pms-pass1-field");

        var billing_state_field = $(this).find("#pms_billing_state");
        var billing_country_field = $(this).find("#pms_billing_country");
        var billing_zip_field = $(this).find("#pms_billing_zip");
        $(billing_state_field).parent().parent().remove().insertBefore(billing_zip_field.parent().parent());
        $(billing_country_field).parent().parent().remove().insertBefore(billing_zip_field.parent().parent());

        var stateSelectField = '<select id="pms_billing_state" name="pms_billing_state">';
        stateSelectField += '<option value=""></option>';
        stateSelectField += '<option value="AL">Alabama</option>';
        stateSelectField += '<option value="AK">Alaska</option>';
        stateSelectField += '<option value="AZ">Arizona</option>';
        stateSelectField += '<option value="AR">Arkansas</option>';
        stateSelectField += '<option value="CA">California</option>';
        stateSelectField += '<option value="CO">Colorado</option>';
        stateSelectField += '<option value="CT">Connecticut</option>';
        stateSelectField += '<option value="DE">Delaware</option>';
        stateSelectField += '<option value="DC">District Of Columbia</option>';
        stateSelectField += '<option value="FL">Florida</option>';
        stateSelectField += '<option value="GA">Georgia</option>';
        stateSelectField += '<option value="HI">Hawaii</option>';
        stateSelectField += '<option value="ID">Idaho</option>';
        stateSelectField += '<option value="IL">Illinois</option>';
        stateSelectField += '<option value="IN">Indiana</option>';
        stateSelectField += '<option value="IA">Iowa</option>';
        stateSelectField += '<option value="KS">Kansas</option>';
        stateSelectField += '<option value="KY">Kentucky</option>';
        stateSelectField += '<option value="LA">Louisiana</option>';
        stateSelectField += '<option value="ME">Maine</option>';
        stateSelectField += '<option value="MD">Maryland</option>';
        stateSelectField += '<option value="MA">Massachusetts</option>';
        stateSelectField += '<option value="MI">Michigan</option>';
        stateSelectField += '<option value="MN">Minnesota</option>';
        stateSelectField += '<option value="MS">Mississippi</option>';
        stateSelectField += '<option value="MO">Missouri</option>';
        stateSelectField += '<option value="MT">Montana</option>';
        stateSelectField += '<option value="NE">Nebraska</option>';
        stateSelectField += '<option value="NV">Nevada</option>';
        stateSelectField += '<option value="NH">New Hampshire</option>';
        stateSelectField += '<option value="NJ">New Jersey</option>';
        stateSelectField += '<option value="NM">New Mexico</option>';
        stateSelectField += '<option value="NY">New York</option>';
        stateSelectField += '<option value="NC">North Carolina</option>';
        stateSelectField += '<option value="ND">North Dakota</option>';
        stateSelectField += '<option value="OH">Ohio</option>';
        stateSelectField += '<option value="OK">Oklahoma</option>';
        stateSelectField += '<option value="OR">Oregon</option>';
        stateSelectField += '<option value="PA">Pennsylvania</option>';
        stateSelectField += '<option value="RI">Rhode Island</option>';
        stateSelectField += '<option value="SC">South Carolina</option>';
        stateSelectField += '<option value="SD">South Dakota</option>';
        stateSelectField += '<option value="TN">Tennessee</option>';
        stateSelectField += '<option value="TX">Texas</option>';
        stateSelectField += '<option value="UT">Utah</option>';
        stateSelectField += '<option value="VT">Vermont</option>';
        stateSelectField += '<option value="VA">Virginia</option>';
        stateSelectField += '<option value="WA">Washington</option>';
        stateSelectField += '<option value="WV">West Virginia</option>';
        stateSelectField += '<option value="WI">Wisconsin</option>';
        stateSelectField += '<option value="WY">Wyoming</option>';
        stateSelectField += '</select>';

        $(billing_state_field).replaceWith(stateSelectField);
        $(billing_country_field).parent().parent().replaceWith('<input type="hidden" id="pms_billing_country" name="pms_billing_country" value="US" />');

        var card_number_field = $(this).find("#pms_card_number");
        $(card_number_field).parent().parent().before('<p id="membership-rate-notice">Monthly recurring membership rate: $14.99</p>');

        $(".pms-first-name-field").find("label").text("First Name *");
        $(".pms-last-name-field").find("label").text("Last Name *");

    });

    //Reg form validation
    $("#pms_register-form").submit(function () {
        var min_cn_length = 2;
        var min_un_length = 7;
        var min_pw_length = 7;
        var min_fn_lenght = 2;
        var min_ln_length = 2;
        var min_jobtitle_length = 2;
        var errors = 0;

        $(this).find("p.error-message").remove();
        $(this).find("li").removeClass("form-error");

        $("#member_company").each(function () {
            if ($(this).val().length < min_cn_length) {
                console.log(this);
                add_form_error(this,min_cn_length);
                errors += 1;
            }
        });

        $("#jobtitle").each(function () {
            if ($(this).val().length < min_jobtitle_length) {
                console.log(this);
                add_form_error(this,min_jobtitle_length);
                errors += 1;
            }
        });

        $("#pms_user_login").each(function () {
            if ($(this).val().length < min_un_length) {
                add_form_error(this,min_un_length);
                errors += 1;
            }
        });

        $("#pms_pass1").each(function () {
            if ($(this).val().length < min_pw_length) {
                add_form_error(this,min_pw_length);
                errors += 1;
            }
        });

        $("#pms_first_name").each(function () {
            if ($(this).val().length < min_fn_lenght) {
                add_form_error(this,min_fn_lenght);
                errors += 1;
            }
        });

        $("#pms_last_name").each(function () {
            if ($(this).val().length < min_ln_length) {
                add_form_error(this,min_ln_length);
                errors += 1;
            }
        });

        $("#pms_user_email").each(function () {
            if (!isValidEmailAddress($(this).val())) {
                add_form_error_message(this,"<p class='error-message'>Must be valid Email.</p>");
                errors += 1;
            }
        });

        if (errors > 0) { $("input[name=pms_register]").removeClass("pms-submit-disabled"); return false; }
    });





    function add_form_error(element,min_length) {
        $(element).parent().append("<p class='error-message'>Type at least "+min_length+" characters.</p>").addClass("form-error");
    }

    function add_form_error_message(element,message) {
        $(element).parent().append(message).addClass("form-error");
    }

    function gag_form_validate(element) {
        element.effect("highlight", { color: "#F2DEDE" }, 1500);
        element.parent().effect('shake');
    }

    function load_ajax_users(form_data,adminurl,loader) {
        var $content = $('#gag_members_directory');
        var $loader = loader;
        var $displayedItems = 10;

        $content.find('button').remove();

        if (!($loader.hasClass('post_loading_loader') || $loader.hasClass('post_no_more_posts'))) {
            $.ajax({
                type: 'POST',
                url: adminurl,
                data: form_data,
                beforeSend : function () {
                    $content.addClass('post_loading_loader').html('');
                },
                success: function (data) {
                    console.log(data);

                    var $data = $(data);
                    if ($data.length) {
                        var $newElements = $data.css({ opacity: 0 });
                        $content.append($data);
                        goToByScroll('gag_members_directory');
                        $newElements.animate({ opacity: 1 });
                        $content.removeClass('post_loading_loader');
                        $content.parent().animate({ opacity: 1 });
                        if ($data.length > $displayedItems) {
                            $content.loadMoreResults({
                                displayedItems: $displayedItems,
                                tag: {
                                    'name': 'section',
                                    'class': 'search-results-item'
                                }
                            });
                        }


                    } else {
                        $content.removeClass('post_loading_loader').addClass('post_no_more_posts').html('Nothing found.');
                        goToByScroll('gag_members_directory');
                        $content.parent().animate({ opacity: 1 });
                    }
                },
                error : function (jqXHR, textStatus, errorThrown) {
                    $content.html($.parseJSON(jqXHR.responseText) + ' :: ' + textStatus + ' :: ' + errorThrown);
                    console.log('Error');
                    console.log(jqXHR);
                },
            });
        }
        return false;
    }

    function isValidEmailAddress(emailAddress) {
        var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
        return pattern.test(emailAddress);
    }

    function goToByScroll(id){
        // Remove "link" from the ID
        id = id.replace("link", "");
        // Scroll
        $('html,body').animate({
                scrollTop: $("#"+id).offset().top-50},
            'slow');
    }

    $('.section-headers-container .section-header').click(function(e) {
        tabClicked = $(this).data('tab');
        if (tabClicked == 'criteria') {
            if (!$('.criteria-tab').hasClass('active')) {
                $('figure.gag-search-results').css('opacity', '0');
            }
            $('.criteria-tab').addClass('active');
            $('.member-tab').removeClass('active');
            $('.search-by-criteria').show();
            $('.find-member').hide();
        } else if (tabClicked == 'member') {
            if (!$('.member-tab').hasClass('active')) {
                $('figure.gag-search-results').css('opacity', '0');
            }
            $('.member-tab').addClass('active');
            $('.criteria-tab').removeClass('active');
            $('.find-member').show();
            $('.search-by-criteria').hide();
        }
    });

    //$('.pms-account-subscription-details-table').each(function() {
    //    $(this).find(".pms-user-email-field");
    //});

    //Replace text on Manage Subscriptions page
    $('.pms-account-subscription-details-table td').each(function() {
        var html = $(this).html();
        html = html.replace('Expiration Date', 'Renewal Date');
        html = html.replace('Abandon', '');
        $(this).html(html);
    });

    $('.pms-account-subscription-details-table').each(function() {
        var html = $(this).next().html();
        html = html.replace('/manage-subscriptions/','/my-account/');
        $(this).next().html(html);
        $(this).after('<div><a href="/automatic-recurring-billing-agreement/" class="small button">User Agreement</a></div><br>');
    });



    }(jQuery);
