window.AudiencePlayerCore = {};

jQuery(function ($) {

    window.AudiencePlayerCore = {

        /**
         * Main config, hydrated in template "templates/audienceplayer-core-javascript.php", is kept here
         */
        CONFIG: {
            isLoggedIn: false,
            isSynchronised: false
        },

        /**
         * Ajax-synchronised API-data is kept here
         */
        STORE: {
            UserDetails: {
                key: 'UserDetails',
                value: null,
                operation: 'query{UserDetails{id,nomadic_articles{id,appr},history_articles{id,appr},entitled_articles{id},user_subscriptions{id,subscription_id,is_valid}}}'
            },
        },

        genericImplementationErrorCode: 2001,

        $currentActiveModal: null,

        /**
         * Initialise Fetch UserDetails in order to set nomadic progress
         */
        init: function () {

            let self = this;

            // Hydrate global AudiencePlayer Library/Config
            self.CONFIG = window.AudiencePlayerLib;

            if (self.CONFIG && self.CONFIG.isLoggedIn) {

                let callback = function () {
                    self.initArticleProgressIndication();
                    self.initEntitledArticleActions();
                };

                // Initialise article play progress indication
                if (self.STORE.UserDetails.value = self.fetchCache(self.STORE.UserDetails.key)) {
                    callback();
                } else {
                    self.refreshStoreUserDetails(callback);
                }

            } else {
                // user not authenticated, clear cache
                self.clearCache();
            }

            // Initialise event listeners
            self.initEventListeners();

            // Set session refresh after 1h inactivity
            self.refreshSession(3600);
        },

        /**
         * Initialise progress indicators on already rendered article carousels/grids
         */
        initArticleProgressIndication: function () {

            let self = this;

            if (self.CONFIG.isNomadicWatchingEnabled) {

                try {

                    let articles = [];

                    // Parse nomadic and history Articles
                    for (let i in this.STORE.UserDetails.value.nomadic_articles) {
                        articles.push(this.STORE.UserDetails.value.nomadic_articles[i]);
                    }
                    for (let i in this.STORE.UserDetails.value.history_articles) {
                        articles.push(this.STORE.UserDetails.value.history_articles[i]);
                    }

                    // Update progress indicators
                    for (let i in articles) {
                        let article = articles[i];
                        let progressPc = article.appr ? parseInt(article.appr * 100) : 0;

                        if (progressPc) {
                            $('.article-id-' + article.id + '.progress-bar')
                                .fadeIn();
                            $('.article-id-' + article.id + '.progress')
                                .animate({width: progressPc + '%'}, 1000);
                        }
                    }

                } catch (e) {
                    // silent
                }
            }
        },

        /**
         * Initialise entitled article actions
         */
        initEntitledArticleActions: function () {

            try {
                for (let i in this.STORE.UserDetails.value.entitled_articles) {
                    let article = this.STORE.UserDetails.value.entitled_articles[i];
                    // hide product buttons for entitled items
                    let productButton = $('.audienceplayer-purchase-product-button.article-id-' + article.id);
                    $(productButton).hide();
                }
            } catch (e) {
                // silent
            }
        },

        /**
         * Initialise event listeners
         */
        initEventListeners: function () {

            let self = this;

            // Setup listeners for modal close buttons
            let $modalButtons = $('.audienceplayer-modal button.close');
            $modalButtons.unbind();
            $modalButtons.click(function () {
                self.closeModal(true);
                return false;
            });

            // Set up keyup events to close modals: Currently only map the ESC key (27)
            $(document).unbind('keyup.key27');
            $(document).bind('keyup.key27', function (event) {
                // escape key
                if (event.which === 27) {
                    if (self.getActiveModal()) {
                        self.closeModal(true);
                    }
                }
                return false;
            });
        },

        /**
         * Call AudiencePlayer API
         * @param action
         * @param operation
         * @param callbackSuccess
         * @param callbackFailure
         */
        callApi: function (action, operation, postData, callbackSuccess, callbackFailure) {

            let data = {
                action: action,
                operation: operation,
            };

            if (postData) {
                for (let key in postData) {
                    data[key] = postData[key];
                }
            }

            $.ajax({
                url: this.CONFIG.siteBaseUrl + '/wp-admin/admin-ajax.php',
                type: 'post',
                dataType: 'json',
                data: data,
            })
                .done(function (response) {
                    if (callbackSuccess) {
                        callbackSuccess(response);
                    }
                })
                .fail(function (response) {
                    if (callbackFailure) {
                        callbackFailure(response);
                    }
                });
        },

        /**
         * Refresh the store UserDetails
         * @param callbackSuccess
         */
        refreshStoreUserDetails: function (callbackSuccess) {

            let self = this;

            // first clear all cache before fetching UserDetails
            self.clearCache();

            if (self.isAuthenticated()) {

                self.callApi(
                    'audienceplayer_api_call',
                    self.STORE.UserDetails.operation,
                    null,
                    function (response) {
                        self.STORE.UserDetails.value = response.data.UserDetails;
                        self.setCache(self.STORE.UserDetails.key, self.STORE.UserDetails.value);
                        if (callbackSuccess) {
                            callbackSuccess(response);
                        }
                    },
                    function (response) {
                        if (response.responseJSON.errors[0].code === 401) {
                            self.clearCache();
                        }
                    }
                );
            }

        },

        refreshSession: function (ttl, isForceSync, onSuccessCallback, onFailureCallback) {

            let self = this;

            let refreshCallback = function () {

                self.callApi(
                    'audienceplayer_sync_session',
                    null,
                    {is_force_sync: isForceSync ? 1 : 0},
                    function (response) {
                        if (self.CONFIG && response.data.token) {
                            self.CONFIG.userBearerToken = response.data.token;
                        }
                        if (self.CONFIG && response.data.expires_in) {
                            self.CONFIG.userBearerTokenTtl = (response.data.expires_in - 60) * 1000;
                        }
                        // Trigger a new refreshSession after given ttl
                        if (self.CONFIG.userBearerTokenTtl && ttl) {
                            self.refreshSession(ttl);
                        }

                        if (onSuccessCallback) {
                            onSuccessCallback();
                        }
                    },
                    onFailureCallback
                );
            };

            if (ttl > 0) {
                setTimeout(refreshCallback, (self.CONFIG && self.CONFIG.userBearerTokenTtl) ? self.CONFIG.userBearerTokenTtl : ttl * 1000);
            } else {
                refreshCallback();
            }
        },

        /**
         * Check if the user is authenticated (based on presence of bearerToken)
         * @returns {string|boolean}
         */
        isAuthenticated: function () {
            return (this.CONFIG.userBearerToken || false) && this.CONFIG.isLoggedIn && this.CONFIG.isSynchronised;
        },

        redirectNonAuthenticatedUser: function () {

            let callbackNonAuthenticated = arguments[0];

            if (!this.CONFIG.isLoggedIn) {

                // user in not logged in, execute redirect action
                this.handleApiErrorCallback(event, this.parseTranslationKey('dialogue_user_not_authenticated'), 401, callbackNonAuthenticated);

            } else if (!this.CONFIG.isSynchronised) {

                // user is logged in, but not correctly synchronised!
                this.handleApiErrorCallback(event, this.parseTranslationKey('dialogue_user_not_synchronised'), 407);

            } else {

                // try to forcefully refresh the session
                this.refreshSession(0, true);
            }
        },

        /**
         * Open the AudiencePlayer EmbedPlayer in a modal window
         * @param articleId
         * @param assetId
         */
        openModalPlayer: function (event, articleId, assetId) {

            let self = this;

            // Check browser support before opening modal
            if (!self.isSupportedBrowser()) {
                self.openModalBrowserNotSupported(event);
                return;
            }

            let callbackNonAuthenticated = arguments[3];
            let callbackNonAuthorised = arguments[4];
            let callbackError = arguments[5];

            try {
                document.querySelector('.media-player').classList.remove('media-player--overlay');

                if (self.CONFIG.EmbedPlayer.isConnected()) {

                    self.CONFIG.EmbedPlayer
                        .castVideo({
                            articleId: articleId,
                            assetId: assetId,
                            token: self.CONFIG.userBearerToken,
                            continueFromPreviousPosition: self.CONFIG.isNomadicWatchingEnabled,
                        })
                        .catch(function (error) {
                            self.handleApiErrorCallback(event, error.message, error.code, callbackNonAuthenticated, callbackNonAuthorised, callbackError);
                        })
                        .finally(() => {
                            document.querySelector('.media-player').classList.remove('media-player--loading');
                            self.clearCache();
                        });

                } else {

                    // Hide the main EmbedPlayer wrapper
                    $('.audienceplayer-modal-video-player-wrapper').hide();
                    // Show the modal
                    self.setActiveModal($('#audienceplayer-modal-video-player'));

                    self.CONFIG.EmbedPlayer
                        .play({
                            selector: '.media-player__video-player',
                            options: self.CONFIG.videoPlayerOptions,
                            articleId,
                            assetId,
                            token: self.CONFIG.userBearerToken,
                            continueFromPreviousPosition: self.CONFIG.isNomadicWatchingEnabled,
                        })
                        .catch(function (error) {
                            $('#audienceplayer-modal-video-player').hide();
                            //alert(error.message + ' [' + error.code + ']');
                            self.handleApiErrorCallback(event, error.message, error.code, callbackNonAuthenticated, callbackNonAuthorised, callbackError);
                        })
                        .finally(() => {

                            // Show the main EmbedPlayer wrapper
                            $('.audienceplayer-modal-video-player-wrapper').fadeIn();
                            self.clearCache();

                            try {
                                // Automatically close modal after player "ended" event has triggered
                                const playerInstance = self.CONFIG.EmbedPlayer.getVideoPlayer();
                                playerInstance.on('ended', () => {
                                    self.closeModal(true);
                                });
                            } catch (e) {
                                //
                            }
                        });
                }
            } catch (e) {
                // @TODO
            }
        },

        /**
         * Open purchase modal for products and subscriptions
         * @param event
         * @param type
         * @param resourceId
         * @param message
         * @param price
         * @param isVoucherValidation
         */
        openModalPurchase: function (event, type, resourceId, message, price, isVoucherValidation) {

            let self = this;

            // Check browser support before opening modal
            if (!self.isSupportedBrowser()) {
                self.openModalBrowserNotSupported(event);
                return false;
            }

            let callbackNonAuthenticated = arguments[6];
            let callbackNonAuthorised = arguments[7];
            let afterPurchaseRedirectAction = arguments[8];
            let callbackError = arguments[9];

            let $currentModal = $('#audienceplayer-modal-dialog-purchase');
            let $voucherCodeInput = $currentModal.find('.voucher-code-input');
            let $validateButton = $currentModal.find('.voucher-validate-button');
            let $acceptButton = $currentModal.find('.ok');
            let $validationResult = $currentModal.find('.voucher-code-validation-result');
            let $price = $currentModal.find('.price');

            try {
                $validateButton.unbind();
                $acceptButton.unbind();
                $voucherCodeInput.val('');
                $currentModal.find('.message').html(message);
                $validationResult.html('');
                $price.html(price);
            } catch (e) {
                // @TODO
            }

            if (!self.isAuthenticated()) {
                self.redirectNonAuthenticatedUser(callbackNonAuthenticated);
                return false;
            }

            if (isVoucherValidation && type !== 'payment_method') {

                let onSuccessCallback = function (response) {
                    if (response.data.PaymentArgumentsValidate) {
                        $validationResult.removeClass('error');
                        $validationResult.html(response.data.PaymentArgumentsValidate.voucher_description);
                        $price.html(response.data.PaymentArgumentsValidate.currency_symbol + ' ' + response.data.PaymentArgumentsValidate.total_price_after);
                    }
                };

                let onFailureCallback = function (response) {
                    let errorMsg = response.responseJSON.errors[0].message;
                    let errorCode = response.responseJSON.errors[0].code;
                    if (errorCode === 400) {
                        errorMsg = self.parseTranslationKey('dialogue_voucher_code_invalid');
                    }
                    // show validation result inline
                    $validationResult.addClass('error');
                    $validationResult.html(errorMsg);
                };

                let argsString = 'product_stack:[{purchase_num:1,id:' + resourceId + '}]';

                if (type === 'subscription') {
                    argsString = 'subscription_id:' + resourceId;
                }

                $validateButton.click(function () {
                    self.validateVoucherCode(
                        $validateButton,
                        $voucherCodeInput.val(),
                        argsString,
                        false,
                        onSuccessCallback,
                        onFailureCallback
                    );
                });

                $currentModal.find('.voucher').show();
            } else {
                $currentModal.find('.voucher').hide();
            }

            $acceptButton.click(function (event) {

                if ($acceptButton.isWorking) {
                    return;
                } else {
                    self.toggleElementIsWorking([$acceptButton], true);
                }

                let voucherCodeOperation = $voucherCodeInput.val() ?
                    ',voucher_code:"' + $voucherCodeInput.val() + '"' :
                    '';

                let operation = '';
                let responseProperty = '';

                switch (type) {

                    case 'product':
                        operation = 'mutation{UserProductAcquire(' +
                            'payment_provider_id:' + self.CONFIG.paymentProviderId +
                            ',product_stack:[{purchase_num:1,id:' + resourceId + '}]' +
                            voucherCodeOperation +
                            ',redirect_url_path:"' + self.fetchCurrentUrl({product_id: resourceId}, afterPurchaseRedirectAction, ['purchase_product_id', 'purchase_subscription_id']) + '"' +
                            '){payment_url}}';
                        responseProperty = 'UserProductAcquire';
                        break;

                    case 'subscription':
                        operation = 'mutation{UserSubscriptionAcquire(' +
                            'payment_provider_id:' + self.CONFIG.paymentProviderId +
                            ',subscription_id:' + resourceId +
                            voucherCodeOperation +
                            ',redirect_url_path:"' + self.fetchCurrentUrl({subscription_id: resourceId}, afterPurchaseRedirectAction, ['purchase_product_id', 'purchase_subscription_id']) + '"' +
                            '){payment_url}}';
                        responseProperty = 'UserSubscriptionAcquire';
                        break;

                    case 'payment_method':
                        operation = 'mutation{UserPaymentAccountPaymentMethodUpdate(' +
                            'user_payment_account_id:' + resourceId +
                            ',redirect_url_path:"' + self.fetchCurrentUrl({user_payment_account_id: resourceId}, afterPurchaseRedirectAction) + '"' +
                            '){payment_url,user_payment_account_order_id,status}}';
                        responseProperty = 'UserPaymentAccountPaymentMethodUpdate';
                        break;
                }

                self.callApi(
                    'audienceplayer_api_call',
                    operation,
                    null,
                    function (response) {

                        self.toggleElementIsWorking([$acceptButton], false);

                        // clear cache
                        window.AudiencePlayerCore.clearCache();

                        // Acquisition was successful
                        if (response.data[responseProperty].payment_url) {
                            // redirect to payment provider
                            window.location.replace(response.data[responseProperty].payment_url);
                        } else {
                            // payment is not necessary
                            self.closeModal();
                            self.openModalAlert(
                                event,
                                self.parseTranslationKey('dialogue_product_purchased_without_payment'),
                                null,
                                self.reloadCurrentPage
                            );
                            self.init();
                        }
                    },
                    function (response) {

                        self.toggleElementIsWorking([$acceptButton], false);

                        let errorMsg = response.responseJSON.errors[0].message;
                        let errorCode = response.responseJSON.errors[0].code;
                        if (errorCode === 3600) {
                            errorMsg = self.parseTranslationKey('dialogue_voucher_code_invalid');
                        }

                        self.handleApiErrorCallback(
                            event,
                            errorMsg,
                            errorCode,
                            callbackNonAuthenticated,
                            callbackNonAuthorised,
                            callbackError
                        );
                    }
                );
            });

            self.setActiveModal($currentModal);

            return false;
        },


        /**
         * Open subscription-switch modal
         *
         * @param event
         * @param userSubscriptionId
         */
        openModalSubscriptionSwitch: function (event, userSubscriptionId) {

            let self = this;

            if (userSubscriptionId) {

                // Check browser support before opening modal
                if (!self.isSupportedBrowser()) {
                    self.openModalBrowserNotSupported(event);
                    return;
                }

                let $currentModal = $('#audienceplayer-modal-dialog-subscription-switch');
                self.setActiveModal($currentModal);

                let $switchSubscriptionButtons = $currentModal.find('button.switch-subscription-button:not(:disabled)');

                let onClickSubscriptionSwitchCallback = function ($targetButton, switchToSubscriptionId) {

                    // Set targetButton to isWorking and unbind all buttons to prevent clicks
                    self.toggleElementIsWorking([$targetButton], true);
                    $switchSubscriptionButtons.unbind();

                    self.callApi(
                        'audienceplayer_api_call',
                        'mutation{UserSubscriptionSwitch(' +
                        'id:' + userSubscriptionId + ',switch_to_subscription_id:' + switchToSubscriptionId +
                        ')}',
                        null,
                        function (response) {

                            // clear cache
                            window.AudiencePlayerCore.clearCache();

                            // Acquisition was successful
                            if (response.data['UserSubscriptionSwitch']) {
                                // reload current page
                                self.reloadCurrentPage();
                            } else {
                                self.toggleElementIsWorking([$targetButton], false);
                                self.handleApiErrorCallback(null, self.CONFIG.translations.dialogue_error_occurred, 500);
                            }
                        },
                        function (response) {

                            self.toggleElementIsWorking([$targetButton], false);

                            let errorMsg = response.responseJSON.errors[0].message;
                            let errorCode = response.responseJSON.errors[0].code;
                            self.handleApiErrorCallback(event, errorMsg, errorCode);
                        }
                    );
                };

                $switchSubscriptionButtons.each(function () {

                    let targetButtonElement = this;
                    let $targetButton = $(targetButtonElement);
                    let switchToSubscriptionId = targetButtonElement.value;

                    if (switchToSubscriptionId) {
                        $targetButton.click(function (event) {
                            onClickSubscriptionSwitchCallback($targetButton, switchToSubscriptionId);
                            return false;
                        });
                    }
                });

            } else {
                // malformed call
                self.openModalErrorMessage('', self.genericImplementationErrorCode);
            }

            return false;
        },

        /**
         * Open a modal alert dialog
         * @param event
         * @param message
         */
        openModalAlert: function (event, message) {

            let self = this;
            let buttonOkLabel = arguments[2];
            let buttonOkOnClick = arguments[3];
            let buttonCancelLabel = arguments[4];
            let buttonCancelOnClick = arguments[5];
            let valueInput = arguments[6];

            let $currentModal = $('#audienceplayer-modal-dialog-alert');

            $currentModal.customCancelAction = null;
            $currentModal.find('.value-validation-message').removeClass('error');
            self.setModalProperty($currentModal, 'value-validation-message', '');

            if (typeof valueInput !== 'undefined') {
                self.setModalProperty($currentModal, 'value-character-input', typeof valueInput === 'string' ? valueInput : '');
                self.setModalProperty($currentModal, 'value-input-container', null, true);
            } else {
                self.setModalProperty($currentModal, 'value-character-input', '');
                self.setModalProperty($currentModal, 'value-input-container', null, false);
            }

            // OK button
            let $modalButtonOk = $currentModal.find('.ok');
            $modalButtonOk.unbind();
            $modalButtonOk.html(buttonOkLabel ? buttonOkLabel : self.parseTranslationKey('button_ok'));

            if (buttonOkOnClick) {
                $modalButtonOk.click(function (event) {
                    buttonOkOnClick(event, $modalButtonOk);
                    return false;
                });
            } else {
                $modalButtonOk.click(function () {
                    self.closeModal();
                    return false;
                });
            }

            // Cancel button
            let $modalButtonCancel = $currentModal.find('.cancel');
            $modalButtonCancel.unbind();

            if (buttonCancelLabel || buttonCancelOnClick) {

                $modalButtonCancel.html(buttonCancelLabel ? buttonCancelLabel : self.parseTranslationKey('button_cancel'));

                // Define custom cancel action, which will also be executed by closeModal
                if (buttonCancelOnClick) {
                    $currentModal.customCancelAction = buttonCancelOnClick;
                }

                $modalButtonCancel.click(function () {
                    window.AudiencePlayerCore.closeModal(true);
                    return false;
                });

                $modalButtonCancel.show();
            } else {
                $modalButtonCancel.hide();
            }

            // Set message and show
            self.setModalProperty($currentModal, 'message', message);
            self.setActiveModal($currentModal);
        },

        openModalBrowserNotSupported: function (event) {

            let message = window.AudiencePlayerBrowserNotSupportedError;

            try {
                if (this.CONFIG.translations.dialogue_not_optimised_for_current_browser) {
                    message = this.parseTranslationKey('dialogue_not_optimised_for_current_browser');
                } else if (window.AudiencePlayerLib.translations.dialogue_not_optimised_for_current_browser) {
                    message = window.AudiencePlayerLib.translations.dialogue_not_optimised_for_current_browser;
                } else if (window.AudiencePlayerBrowserNotSupportedError) {
                    message = window.AudiencePlayerBrowserNotSupportedError;
                }
            } catch (e) {
                //
            }

            this.openModalAlert(event, message, 'OK');
        },

        openModalErrorMessage: function (errorMessage, errorCode) {
            let self = this;
            self.closeModal(true);
            self.openModalAlert(null, self.assembleDefaultErrorMsg(errorMessage, errorCode));
        },

        /**
         * Close any open modal window
         * @param isExecuteCustomCancelAction
         */
        closeModal: function (isExecuteCustomCancelAction) {

            let $currentModal = this.getActiveModal();

            try {

                if (isExecuteCustomCancelAction && $currentModal.customCancelAction) {
                    $currentModal.customCancelAction();
                }

                $currentModal.fadeOut();

                // if current modal is the video player, ensure it is properly destroyed
                if ($currentModal[0].id === 'audienceplayer-modal-video-player') {
                    this.CONFIG.EmbedPlayer.destroy();
                    this.clearCache();
                }

                this.setActiveModal(null);

            } catch (e) {
                // @TODO
            }
        },

        setActiveModal: function ($modal) {
            if ($modal) {
                $modal.fadeIn();
            }
            this.$currentActiveModal = $modal;
        },

        getActiveModal: function () {
            return this.$currentActiveModal;
        },

        setModalProperty: function ($modal, targetClass, targetValue, isShow) {

            let $targetModal = $modal ? $modal : this.getActiveModal();

            if ($targetModal) {

                let $property = $targetModal.find('.' + targetClass);

                if (targetValue !== null && targetValue !== undefined) {
                    if ($property.is('input')) {
                        $property.val(targetValue);
                    } else {
                        $property.html(targetValue);
                    }
                }

                if (true === isShow) {
                    $property.show();
                } else if (false === isShow) {
                    $property.hide();
                }
            }
        },

        /**
         * Validate a voucher code
         * @param $validateButton
         * @param voucherCode
         * @param argsString
         * @param isSoloValidation
         * @param onSuccessCallback
         * @param onFailureCallback
         */
        validateVoucherCode: function ($validateButton, voucherCode, argsString, isSoloValidation, onSuccessCallback, onFailureCallback) {

            let self = this;

            if ($validateButton.isWorking) {
                return;
            } else {
                self.toggleElementIsWorking([$validateButton], true);
            }

            if (isSoloValidation) {
                argsString = argsString ? argsString + ',is_solo_validation:true' : 'is_solo_validation:true';
            } else {
                argsString = argsString ? argsString + ',is_solo_validation:false' : 'is_solo_validation:false';
            }

            if (voucherCode) {

                let operation = 'query{PaymentArgumentsValidate(' +
                    'voucher_code:"' + voucherCode + '",' + argsString +
                    '){voucher_validity,voucher_description,total_price_after,currency,currency_symbol}}';

                self.callApi(
                    'audienceplayer_api_call',
                    operation,
                    null,
                    function (response) {
                        self.toggleElementIsWorking([$validateButton], false);
                        // Acquisition was successful
                        onSuccessCallback(response);
                    },
                    function (response) {
                        self.toggleElementIsWorking([$validateButton], false);
                        onFailureCallback(response);
                    }
                );

            } else {
                self.toggleElementIsWorking([$validateButton], false);
                try {
                    onFailureCallback({responseJSON: {errors: [{code: 400}]}});
                } catch (e) {
                    //
                }
            }
        },

        assembleDefaultErrorMsg: function (errorMessage, errorCode) {
            let self = this;
            return self.parseTranslation(self.CONFIG.translations.dialogue_error_occurred + ': ' + errorMessage, {error_code: errorCode});
        },

        /**
         * Handle api error callbacks
         * @param event
         * @param errorMessage
         * @param errorCode
         * @param callbackNonAuthenticated
         * @param callbackNonAuthorised
         * @param callbackError
         */
        handleApiErrorCallback: function (event, errorMessage, errorCode, callbackNonAuthenticated, callbackNonAuthorised, callbackError) {

            let self = this;

            //alert(errorMessage + ' [' + errorCode + ']');
            let errorMsg = self.assembleDefaultErrorMsg(errorMessage, errorCode);

            if (errorCode === 401) {

                // if user is assumed be (lazily) authenticated, the stored accessToken is likely corrupt/invalid
                if (self.isAuthenticated()) {

                    // try to forcefully refresh the session
                    self.refreshSession(0, true, function (response) {
                        self.closeModal(true);
                        self.openModalAlert(null, 'Please try again!');
                    }, function (response) {
                        self.closeModal(true);
                        if (response.responseJSON.errors[0].code === 404) {
                            // user was not found during forced synchronisation, admin likely needs to resync
                            self.openModalAlert(null, self.parseTranslationKey('dialogue_user_not_synchronised'));
                        } else {
                            if (callbackNonAuthenticated) {
                                callbackNonAuthenticated();
                            } else {
                                self.openModalAlert(null, self.parseTranslationKey('dialogue_user_not_authenticated'));
                            }
                        }
                    });
                    return;

                } else if (callbackNonAuthenticated) {

                    self.closeModal(true);
                    try {
                        callbackNonAuthenticated();
                    } catch (e) {
                    }
                    return;

                } else {

                    self.closeModal(true);
                    errorMsg = self.parseTranslationKey('dialogue_user_not_authenticated');
                }

            } else if (errorCode === 402) {

                self.closeModal(true);
                if (callbackNonAuthorised) {
                    try {
                        callbackNonAuthorised();
                    } catch (e) {
                    }
                    return;
                } else {
                    errorMsg = self.parseTranslationKey('dialogue_payment_required_for_video');
                }

            } else if (errorCode === 403) {

                self.closeModal(true);
                errorMsg = self.parseTranslationKey('dialogue_video_not_available_in_region');

            } else if (errorCode === 407) {

                self.closeModal(true);
                errorMsg = self.parseTranslationKey('dialogue_user_not_synchronised');

            } else if (errorCode === 3201) {

                self.closeModal(true);
                errorMsg = self.parseTranslationKey('dialogue_subscription_already_valid');

            } else if (errorCode === 3800 || errorCode === 409) {

                self.closeModal(true);
                errorMsg = self.parseTranslationKey('dialogue_product_already_purchased');

            } else if (callbackError) {

                self.closeModal(true);
                try {
                    callbackError();
                } catch (e) {
                }

            } else {
                self.closeModal(false);
            }

            self.openModalAlert(null, errorMsg);
        },

        /**
         * Toggle user-subscription status
         * @param event
         * @param type
         * @param resourceId
         * @param message
         * @param price
         * @param isVoucherValidation
         */
        toggleUserSubscriptionStatus: function (event, userSubscriptionId, isRenew) {

            let self = this;
            let $elementToggle = $(event);

            let callbackNonAuthenticated = arguments[3];
            let callbackError = arguments[4];

            if (!self.isAuthenticated()) {
                self.redirectNonAuthenticatedUser(callbackNonAuthenticated);
                return;
            } else if ($elementToggle.isWorking) {
                return;
            } else {
                self.toggleGenericModalLoader(true);
                self.toggleElementIsWorking([$elementToggle], true);
            }

            let operation = '';
            let responseProperty = '';

            if (isRenew) {
                operation = 'mutation{UserSubscriptionUnSuspend(id:' + userSubscriptionId + ')}';
                responseProperty = 'UserSubscriptionUnSuspend';
            } else {
                operation = 'mutation{UserSubscriptionSuspend(id:' + userSubscriptionId + ')}';
                responseProperty = 'UserSubscriptionSuspend';
            }

            self.callApi(
                'audienceplayer_api_call',
                operation,
                null,
                function (response) {

                    if (isRenew) {
                        $('.subscription-info .invoiced-at .value').removeClass('disabled');
                    } else {
                        $('.subscription-info .invoiced-at .value').addClass('disabled');
                    }

                    self.toggleGenericModalLoader(false);
                    self.toggleElementIsWorking([$elementToggle], false);
                },
                function (response) {

                    // revert toggle status
                    $elementToggle.prop('checked', !isRenew);

                    self.toggleGenericModalLoader(false);
                    self.toggleElementIsWorking([$elementToggle], false);

                    let errorMsg = response.responseJSON.errors[0].message;
                    let errorCode = response.responseJSON.errors[0].code;

                    self.handleApiErrorCallback(
                        event,
                        errorMsg,
                        errorCode,
                        callbackNonAuthenticated,
                        null,
                        callbackError
                    );
                }
            );
        },

        /**
         * Toggle user notifiable status
         * @param event
         * @param isNotifiable
         * @param $phoneNumber
         */
        toggleUserNotifiableStatus: function (event, isNotifiable, $phoneNumber) {

            let self = this;
            let $elementToggle = $(event);
            let callbackNonAuthenticated = arguments[3];
            let callbackError = arguments[4];

            // if notifiable, proceed with step 1/2 and validate phoneNumber. Else fall-through and attempt to set notifiable:none
            if (isNotifiable) {

                self.notifiableVerifyUserPhoneNumber(event, isNotifiable, $phoneNumber, callbackNonAuthenticated, callbackError);

            } else if (!self.isAuthenticated()) {

                self.redirectNonAuthenticatedUser(callbackNonAuthenticated);

            } else if (!$elementToggle.isWorking) {

                self.toggleGenericModalLoader(true);
                self.toggleElementIsWorking([$elementToggle], true);

                self.callApi(
                    'audienceplayer_api_call',
                    'mutation{UserDetailsUpdate(notifiable:none){id,notifiable}}',
                    null,
                    function (response) {

                        self.toggleGenericModalLoader(false);
                        self.toggleElementIsWorking([$elementToggle], false);
                    },
                    function (response) {

                        // revert toggle status
                        $elementToggle.prop('checked', !isNotifiable);

                        self.toggleGenericModalLoader(false);
                        self.toggleElementIsWorking([$elementToggle], false);

                        let errorMsg = response.responseJSON.errors[0].message;
                        let errorCode = response.responseJSON.errors[0].code;

                        self.handleApiErrorCallback(
                            event,
                            errorMsg,
                            errorCode,
                            callbackNonAuthenticated,
                            null,
                            callbackError
                        );
                    }
                );
            }
        },

        /**
         * toggleUserNotifiableStatus - step 1/2: verify phone number
         * @param event
         * @param isNotifiable
         * @param $phoneNumber
         * @param callbackNonAuthenticated
         * @param callbackError
         */
        notifiableVerifyUserPhoneNumber: function (event, isNotifiable, $phoneNumber, callbackNonAuthenticated, callbackError) {

            let self = this;
            let $elementToggle = $(event);

            if ($elementToggle.isWorking) {

                return;

            } else if (!self.isAuthenticated()) {

                self.redirectNonAuthenticatedUser(callbackNonAuthenticated);
                return;

            } else {

                let cancelAction = function () {
                    // revert toggle status
                    $elementToggle.prop('checked', !isNotifiable);
                };

                let okAction = function (ev, $okButton, $currentModal) {

                    if ($okButton.isWorking) {
                        return;
                    } else {
                        self.toggleElementIsWorking([$okButton], true);
                    }

                    // update phone number
                    $phoneNumber.val($('#audienceplayer-modal-dialog-alert-value-input').val());

                    let continueAction = function () {
                        self.openModalAlert(
                            event,
                            self.parseTranslationKey('dialogue_phone_number_change_request'),
                            self.parseTranslationKey('button_next'),
                            function () {
                                self.notifiableVerifyPairingCode(event, isNotifiable, $phoneNumber, callbackNonAuthenticated, callbackError);
                            },
                            self.parseTranslationKey('button_cancel'),
                            cancelAction
                        );
                    };

                    self.callApi(
                        'audienceplayer_api_call',
                        'mutation{UserDetailsUpdate(phone:"' + $phoneNumber.val() +
                        '",notifiable:' + (isNotifiable ? 'sms' : 'none') + '){id,phone,notifiable}}',
                        null,
                        function (response) {

                            self.toggleElementIsWorking([$elementToggle, $okButton], false);

                            if (response.data.UserDetailsUpdate.notifiable === 'sms') {
                                self.closeModal();
                            } else {
                                // phone number has likely changed, since notifiable hasn't been set to 'sms'
                                continueAction();
                            }
                        },
                        function (response) {

                            self.toggleElementIsWorking([$elementToggle, $okButton], false);

                            let errorMsg = response.responseJSON.errors[0].message;
                            let errorCode = response.responseJSON.errors[0].code;

                            if (errorCode === 400) {
                                // phone number incorrect
                                self.getActiveModal().find('.value-validation-message').addClass('error');
                                self.setModalProperty(null, 'value-validation-message', self.parseTranslationKey('form_validation_error_phone_number'));
                            } else if (errorCode === 402) {
                                self.openModalErrorMessage(self.parseTranslationKey('dialogue_notification_enabling_not_authorised'), errorCode);
                            } else if (errorCode === 403) {
                                continueAction();
                            } else {
                                self.handleApiErrorCallback(
                                    event,
                                    errorMsg,
                                    errorCode,
                                    callbackNonAuthenticated,
                                    null,
                                    callbackError
                                );
                            }
                        }
                    );
                };

                self.openModalAlert(
                    event,
                    self.parseTranslationKey('form_validation_error_phone_number_fill_first'),
                    self.parseTranslationKey('button_ok'),
                    okAction,
                    self.parseTranslationKey('button_cancel'),
                    cancelAction,
                    $phoneNumber.val()
                );
            }
        },

        /**
         * toggleUserNotifiableStatus - step 2/2: verify code
         * @param event
         * @param isNotifiable
         * @param $phoneNumber
         * @param callbackNonAuthenticated
         * @param callbackError
         */
        notifiableVerifyPairingCode: function (event, isNotifiable, $phoneNumber, callbackNonAuthenticated, callbackError) {

            let self = this;
            let $elementToggle = $(event);

            if ($elementToggle.isWorking) {

                return;

            } else if (!self.isAuthenticated()) {

                self.redirectNonAuthenticatedUser(callbackNonAuthenticated);
                return;

            } else {

                let errorCallback = function (response) {

                    let errorMsg = response.responseJSON.errors[0].message;
                    let errorCode = response.responseJSON.errors[0].code;

                    self.handleApiErrorCallback(
                        event,
                        errorMsg,
                        errorCode,
                        callbackNonAuthenticated
                    );
                };

                self.callApi(
                    'audienceplayer_api_call',
                    'mutation{UserDetailsPhoneVerifyRequest}',
                    null,
                    function (response) {

                        self.toggleElementIsWorking([$elementToggle], false);

                        let okAction = function (ev, $okButton, $currentModal) {

                            if ($okButton.isWorking) {
                                return;
                            } else {
                                self.toggleElementIsWorking([$okButton], true);
                            }

                            let operation = 'mutation{' +
                                'resultVerify:UserDetailsPhoneVerifyValidate(code:"' + $('#audienceplayer-modal-dialog-alert-value-input').val() + '")' +
                                'resultUpdate:UserDetailsUpdate(phone:"' + $phoneNumber.val() + '",notifiable:' + (isNotifiable ? 'sms' : 'none') + '){id,phone,notifiable}' +
                                '}';

                            self.callApi(
                                'audienceplayer_api_call',
                                operation,
                                null,
                                function (response) {

                                    self.toggleElementIsWorking([$elementToggle, $okButton], false);
                                    self.openModalAlert(event, self.parseTranslationKey('dialogue_phone_number_verified'));

                                },
                                function (response) {
                                    if (!response.responseJSON.data.resultVerify) {
                                        self.getActiveModal().find('.value-validation-message').addClass('error');
                                        self.setModalProperty(null, 'value-validation-message', self.parseTranslationKey('dialogue_error_phone_number_pairing_code_not_valid'));
                                        self.toggleElementIsWorking([$okButton], false);
                                    } else {
                                        errorCallback();
                                    }
                                }
                            );
                        };

                        let cancelAction = function () {
                            // revert toggle status
                            $elementToggle.prop('checked', !isNotifiable);
                        };

                        self.openModalAlert(
                            event,
                            self.parseTranslationKey('dialogue_phone_number_verify_via_text_message'),
                            self.parseTranslationKey('button_ok'),
                            okAction,
                            self.parseTranslationKey('button_cancel'),
                            cancelAction,
                            true
                        );
                    },
                    errorCallback
                );
            }
        },

        /**
         * Redeem user account voucher code
         * @param event
         * @param voucherCode
         * @param paymentProviderId
         * @param subscriptionId
         * @param userSubscriptionId
         */
        redeemUserAccountVoucherCode: function (event, voucherCode, paymentProviderId, subscriptionId, userSubscriptionId) {

            let self = this;
            let $validateButton = $(event);

            voucherCode = voucherCode.trim();

            let onSuccessCallback = function (response) {

                self.openModalAlert(
                    null,
                    response.data['PaymentArgumentsValidate'].voucher_description,
                    self.parseTranslationKey('button_payment_accept_complete'),
                    function (event, $modalButton) {

                        if ($modalButton.isWorking) {
                            return;
                        } else {
                            self.toggleElementIsWorking([$modalButton], true);
                        }

                        let operation = 'mutation{UserPaymentVoucherRedeem(' +
                            'code:"' + voucherCode + '",' +
                            'payment_provider_id:' + paymentProviderId + ',' +
                            'subscription_id:' + subscriptionId + ',' +
                            'user_subscription_id:' + userSubscriptionId + ',' +
                            'redirect_url_path:"' + self.fetchCurrentUrl() + '"' +
                            '){payment_url}}';

                        self.callApi(
                            'audienceplayer_api_call',
                            operation,
                            null,
                            function (response) {
                                self.toggleElementIsWorking([$modalButton], false);

                                // clear cache
                                window.AudiencePlayerCore.clearCache();

                                // Acquisition was successful
                                if (response.data['UserPaymentVoucherRedeem'].payment_url) {
                                    // redirect to payment provider
                                    window.location.replace(response.data['UserPaymentVoucherRedeem'].payment_url);
                                } else {
                                    // payment is not necessary
                                    self.closeModal();
                                    self.openModalAlert(
                                        event,
                                        self.parseTranslationKey('dialogue_product_purchased_without_payment'),
                                        null,
                                        self.reloadCurrentPage
                                    );
                                    self.init();
                                }
                            },
                            function (response) {
                                self.toggleElementIsWorking([$modalButton], false);

                                let errorMsg = response.responseJSON.errors[0].message;
                                let errorCode = response.responseJSON.errors[0].code;
                                if (errorCode === 422) {
                                    self.openModalErrorMessage(self.parseTranslationKey('dialogue_voucher_code_applicable_subscription_unknown'), errorCode);
                                } else if (errorCode === 3600) {
                                    self.openModalErrorMessage(self.parseTranslationKey('dialogue_voucher_code_invalid'), errorCode);
                                } else if (errorCode >= 400) {
                                    self.openModalErrorMessage(self.parseTranslationKey('dialogue_voucher_code_not_applicable'), errorCode);
                                } else {
                                    self.handleApiErrorCallback(event, errorMsg, errorCode);
                                }
                            }
                        );
                    }
                );
            };

            let onFailureCallback = function (response) {

                let errorMsg = response.responseJSON.errors[0].message;
                let errorCode = response.responseJSON.errors[0].code;
                if (errorCode === 422) {
                    self.openModalErrorMessage(self.parseTranslationKey('dialogue_voucher_code_applicable_subscription_unknown'), errorCode);
                } else if (errorCode === 3600) {
                    self.openModalErrorMessage(self.parseTranslationKey('dialogue_voucher_code_invalid'), errorCode);
                } else if (errorCode >= 400) {
                    self.openModalErrorMessage(self.parseTranslationKey('dialogue_voucher_code_not_applicable'), errorCode);
                } else {
                    self.handleApiErrorCallback(event, errorMsg, errorCode);
                }

            };

            self.validateVoucherCode($validateButton, voucherCode, 'subscription_id:' + subscriptionId, true, onSuccessCallback, onFailureCallback);
        },

        /**
         * Claim device pairing code
         * @param event
         * @param pairingCode
         */
        claimDevicePairingCode: function (event, pairingCode) {

            let self = this;
            let $validateButton = $(event);

            if (pairingCode = pairingCode.trim()) {

                if ($validateButton.isWorking) {
                    return;
                } else {
                    self.toggleElementIsWorking([$validateButton], true);
                }

                let operation = 'mutation{UserDevicePairingClaim(' +
                    'pairing_code:"' + pairingCode + '"' +
                    '){uuid name created_at}}';

                self.callApi(
                    'audienceplayer_api_call',
                    operation,
                    null,
                    function (response) {
                        self.toggleElementIsWorking([$validateButton], false);
                        self.closeModal();
                        self.openModalAlert(
                            event,
                            self.parseTranslationKey('dialogue_device_successfully_paired'),
                            null,
                            self.reloadCurrentPage
                        );
                    },
                    function (response) {
                        self.toggleElementIsWorking([$validateButton], false);

                        let errorMsg = response.responseJSON.errors[0].message;
                        let errorCode = response.responseJSON.errors[0].code;
                        if (errorCode === 400) {
                            self.openModalErrorMessage(self.parseTranslationKey('dialogue_pairing_code_not_valid'), errorCode);
                        } else {
                            self.handleApiErrorCallback(event, errorMsg, errorCode);
                        }
                    }
                );
            }
        },

        /**
         * Remove an already paired device
         * @param event
         * @param deviceId
         * @param deviceName
         */
        removePairedDevice: function (event, deviceId, deviceName) {

            let self = this;

            if (deviceId) {

                let deviceNameText = deviceName ? '<br/><div class="device-name">"' + deviceName + '"</div>' : '';

                self.openModalAlert(
                    null,
                    self.parseTranslationKey('dialogue_device_confirm_unpairing') + deviceNameText,
                    null,
                    function (event, $modalButton) {

                        self.toggleElementIsWorking([$modalButton], true);

                        let operation = 'mutation{UserDevicePairingDelete(device_id:' + deviceId + ')}';

                        self.callApi(
                            'audienceplayer_api_call',
                            operation,
                            null,
                            function (response) {
                                self.toggleElementIsWorking([$modalButton], false);
                                self.closeModal();
                                self.openModalAlert(
                                    event,
                                    self.parseTranslationKey('dialogue_device_successfully_unpaired'),
                                    null,
                                    self.reloadCurrentPage
                                );
                            },
                            function (response) {
                                self.toggleElementIsWorking([$modalButton], false);
                                let errorMsg = self.parseTranslationKey('dialogue_device_not_unpaired');
                                let errorCode = response.responseJSON.errors[0].code;
                                self.handleApiErrorCallback(event, errorMsg, errorCode);
                            }
                        );
                    },
                    self.parseTranslationKey('button_cancel')
                );
            }
        },

        /* ### HELPERS ### */

        isset: function () {
            if (arguments.length > 0) {
                for (let i = 0; i < arguments.length; i++) {
                    if (typeof arguments[i] === 'undefined') {
                        return false;
                    }
                }
                return true;
            } else {
                return false;
            }
        },

        isSupportedBrowser: function () {

            // Nasic check to ensure browser is in the Internet Explorer family (version <= 11)
            let isIE = /MSIE|Trident/.test(window.navigator.userAgent);
            return !isIE;
        },

        setCache: function (key, value) {
            try {
                //if (typeof value === 'object' || typeof value === 'array') {}
                return window.localStorage.setItem(key, JSON.stringify(value));
            } catch (e) {
                return null;
            }
        },

        fetchCache: function (key) {
            try {
                return JSON.parse(window.localStorage.getItem(key));
            } catch (e) {
                return null;
            }
        },

        clearCache: function () {
            for (let i in this.STORE) {
                this.removeCache(this.STORE[i].key);
            }
        },

        removeCache: function (key) {
            try {
                return window.localStorage.removeItem(key);
            } catch (e) {
                return null;
            }
        },

        parseCurrentUrlQsa: function (paramsObject) {

            let qsa = window.location.search;

            for (let key in paramsObject) {

                if (qsa) {

                    let re = new RegExp('(\\?|&)' + key + '=([^&]*)(&|$)');

                    if (re.test(qsa)) {
                        qsa = qsa.replace(re, '$1' + key + '=' + paramsObject[key] + '$3');
                    } else {
                        qsa += '&' + key + '=' + paramsObject[key];
                    }

                } else {
                    qsa += '?' + key + '=' + paramsObject[key];
                }
            }

            return qsa;
        },

        removeQsaParametersFromString: function (paramsObject) {
            let qsa = arguments[1] ? arguments[1] : window.location.search;

            if (qsa) {

                for (let key in paramsObject) {

                    let param = Array.isArray(paramsObject) ? paramsObject[key] : key;

                    let re = new RegExp('(\\?|&)(' + param + '=[^&]*(&|$))');

                    if (re.test(qsa)) {
                        qsa = qsa.replace(re, '$1');
                    }
                }
            }

            return qsa;
        },

        removeQsaParametersFromCurrentLocation: function (paramsObject) {

            if (paramsObject) {

                let sanitisedUrl = new URL(window.location);

                for (let key in paramsObject) {

                    let param = Array.isArray(paramsObject) ? paramsObject[key] : key;
                    sanitisedUrl.searchParams.delete(param);
                }

                window.history.pushState({}, "", sanitisedUrl);
            }
        },

        fetchCurrentUrl: function (queryParamObject) {

            let ret = window.location.href.split('?')[0];

            if (queryParamObject && arguments[1]) {
                queryParamObject.after_purchase_action = arguments[1];
            } else if (arguments[1]) {
                queryParamObject = {after_purchase_action: arguments[1]};
            }

            let qsa = this.parseCurrentUrlQsa(queryParamObject);

            if (qsa) {
                ret += qsa;
            }

            if (arguments[2]) {
                ret = this.removeQsaParametersFromString(arguments[2], ret);
            }

            return ret;
        },

        toggleElementIsWorking: function ($elements, status) {

            let className = arguments[2] ? arguments[2] : 'audienceplayer-button-loading';

            for (let i in $elements) {
                if (status) {
                    $elements[i].isWorking = true;
                    $elements[i].addClass(className);
                    $elements[i].attr("disabled", true);
                } else {
                    $elements[i].isWorking = false;
                    $elements[i].removeClass(className);
                    $elements[i].attr("disabled", false);
                }
            }
        },

        toggleGenericModalLoader: function (status) {
            if (status) {
                $('#audienceplayer-modal-generic-loader').fadeIn();
            } else {
                $('#audienceplayer-modal-generic-loader').fadeOut();
            }
        },

        reloadCurrentPage: function () {
            window.location.reload();
        },

        parseTranslation: function (translation, vars) {

            if (translation) {

                // replaced given variables
                for (let key in vars) {

                    let re = new RegExp('{{\\s*' + key + '\\s*}}');

                    if (re.test(translation)) {
                        translation = translation.replace(re, vars[key]);
                    }
                }

                // remove all unused variables
                let re = new RegExp('{{.*}}');
                translation = translation.replace(re, '');
            }

            return translation;
        },

        parseTranslationKey: function (key, vars) {
            return this.parseTranslation(this.CONFIG.translations[key], vars);
        }

    };

    // Initialise AudiencePlayerCore
    window.AudiencePlayerCore.init();

});
