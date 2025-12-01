/**
 * Capitito Inventory Management System - JavaScript (FULL, with Discount support + Money mask + Offline Orders)
 * 
 * @package Capitito_IMS
 * @version 2.0.0
 * Author: Okonudo EseAbasi - Bendless Tech
 *
 * Changes in 2.0.0:
 * - Added offline order functionality with IndexedDB
 * - Orders saved locally when offline and synced when online
 * - Visual indicators for offline/online status
 * - Pending orders counter and manual sync trigger
 *
 * Changes in 1.2.0:
 * - Admin items: Archive/Unarchive instead of Delete.
 * - Adds "Show archived" toggle in Admin panel.
 * - Archived items are visually greyed with an "Archived" badge and can be unarchived.
 *
 * Changes in 1.1.8:
 * - Grand Total color on the order page changed from red to sparkling white (with soft glow).
 *
 * Changes in 1.1.7:
 * - Smart zero trimming for Quantity inputs.
 *
 * Changes in 1.1.6:
 * - Order History "View" modal now shows combo payment breakdown (₦ cash/card/transfer).
 * - Payment method badge in history uses human-friendly label (e.g., CASH + CARD).
 * - Keeps discount as NAIRA per item.
 */

(function($) {
    'use strict';

    // ========================================
    // GLOBAL VARIABLES
    // ========================================
    let selectedItems = [];
    let currentPage = 1;
    let perPage = 20;

    // Small helpers
    const money = n => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const parseNum = v => {
        if (v === null || v === undefined) return 0;
        const s = String(v).replace(/,/g, '').replace(/[^\d.-]/g, '');
        const n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    };

    // Turn "cash_card_transfer" into "CASH + CARD + TRANSFER"
    function formatPaymentMethodLabel(method) {
        if (!method) return '';
        return String(method)
            .split('_')
            .filter(Boolean)
            .map(s => s.toUpperCase())
            .join(' + ');
    }

    // Money mask helper for inputs
    function attachMoneyMask(selectors) {
        const $inputs = $(selectors);
        if ($inputs.length === 0) return;

        // Ensure they are text to allow commas, but keep numeric keyboard on mobile
        $inputs.attr({ type: 'text', inputmode: 'decimal', autocomplete: 'off' });

        // Format all on init
        $inputs.each(function() {
            const v = parseNum(this.value);
            this.value = v || this.value ? money(v) : this.value;
        });

        // On focus: unformat for easy editing
        $(document).off('focus.moneyMask', selectors).on('focus.moneyMask', selectors, function() {
            const raw = parseNum(this.value);
            this.value = raw ? raw.toFixed(2).replace(/\.00$/, '.00') : '';
            setTimeout(() => { try { this.setSelectionRange(this.value.length, this.value.length); } catch(e){} }, 0);
        });

        // While typing: do not add commas (prevents cursor jumps), but update any summaries
        $(document).off('input.moneyMask', selectors).on('input.moneyMask', selectors, function() {
            let cleaned = String(this.value).replace(/[^\d.-]/g, '');
            // Normalize leading zeros: keep "0.xxx", but turn "00", "0005" to "5"
            if (cleaned.length > 1 && cleaned[0] === '0' && cleaned[1] !== '.') {
                cleaned = cleaned.replace(/^0+(?=\d)/, '');
            }
            if (cleaned !== this.value) this.value = cleaned;
            $(this).trigger('money:changed');
        });

        // On blur: format nicely with commas
        $(document).off('blur.moneyMask', selectors).on('blur.moneyMask', selectors, function() {
            const v = parseNum(this.value);
            this.value = money(v);
            $(this).trigger('money:changed');
        });
    }

    // Inject sparkling white style for GRAND TOTAL
    function injectGrandTotalSparkleStyle() {
        if (document.getElementById('grand-total-sparkle-style')) return;
        const css = `
            /* Sparkling white for the Grand Total on Order page */
            .grand-total-section .grand-total-display .currency,
            .grand-total-section .grand-total-display #grandTotalAmount {
                color: #FFFFFF !important;
                text-shadow:
                    0 0 3px rgba(255,255,255,0.85),
                    0 0 8px rgba(255,255,255,0.55),
                    0 0 14px rgba(255,255,255,0.35);
            }
        `;
        const style = document.createElement('style');
        style.id = 'grand-total-sparkle-style';
        style.type = 'text/css';
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }

    // ========================================
    // DOCUMENT READY
    // ========================================
    $(document).ready(function() {
        injectGrandTotalSparkleStyle();

        initializeDigitalClock();
        initializeModals();
        initializeOrderPage();
        initializeOrderHistory();
        initializeStockPage();
        initializeStockHistory();
        initializeProductSummary();
        initializeFinancialSummary();
        initializeFinancialHistory();
        initializeReconciliation();
        initializeReconciliationHistory();
        initializeAdminPanel();
        initializeHealthDashboard();
    });

    // ========================================
    // DIGITAL CLOCK
    // ========================================
    function initializeDigitalClock() {
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeString = `${hours}:${minutes}:${seconds}`;
            $('.digital-clock').text(timeString);
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    // ========================================
    // MODAL FUNCTIONS
    // ========================================
    function initializeModals() {
        // Open modal
        $(document).on('click', '[data-modal-open]', function() {
            const modalId = $(this).data('modal-open');
            $(`#${modalId}`).addClass('active');
        });

        // Close modal
        $(document).on('click', '.modal-close, [data-modal]', function() {
            const modalId = $(this).data('modal');
            if (modalId) {
                $(`#${modalId}`).removeClass('active');
            } else {
                $(this).closest('.capitito-modal').removeClass('active');
            }
        });

        // Close modal on outside click
        $(document).on('click', '.capitito-modal', function(e) {
            if ($(e.target).hasClass('capitito-modal')) {
                $(this).removeClass('active');
            }
        });

        // Prevent modal close on content click
        $(document).on('click', '.modal-content', function(e) {
            e.stopPropagation();
        });
    }

    function openModal(modalId) {
        $(`#${modalId}`).addClass('active');
    }

    function closeModal(modalId) {
        $(`#${modalId}`).removeClass('active');
    }

    // ========================================
    // ORDER PAGE (Discount is NAIRA amount per item, not percent) + Money mask + OFFLINE SUPPORT
    // ========================================
    function initializeOrderPage() {
        if ($('#orderForm').length === 0) return;

        // Attach money mask
        attachMoneyMask('#paymentBreakdownSection input, .payment-split-input, #cashAmount, #cardAmount, #transferAmount, .item-discount');

        let currentGrandTotal = 0;

        // Smart zero trimming for Quantity inputs
        $(document).off('keydown.qtyTrim').on('keydown.qtyTrim', '.item-quantity', function(e) {
            const key = e.key;
            if (/^\d$/.test(key) && /^0+$/.test(this.value)) {
                this.value = key;
                e.preventDefault();
                $(this).trigger('input');
            }
        });
        $(document).off('input.qtyTrim').on('input.qtyTrim', '.item-quantity', function() {
            let v = String($(this).val() || '');
            if (v.length > 1) {
                const trimmed = v.replace(/^0+(?=\d)/, '');
                if (trimmed !== v) $(this).val(trimmed);
            }
        });

        // Recalculate a single row
        function recalcRow($row) {
            const $qty  = $row.find('.item-quantity');
            const $disc = $row.find('.item-discount');
            const price = parseFloat($qty.data('item-price')) || 0;
            const qty   = parseFloat($qty.val()) || 0;

            const subtotal = price * qty;

            let discAmount = $disc.length ? parseNum($disc.val()) : 0;
            if (discAmount < 0) discAmount = 0;
            if (discAmount > subtotal) discAmount = subtotal;

            const discountPercent = subtotal > 0 ? (100 * discAmount / subtotal) : 0;
            const lineTotal = subtotal - discAmount;

            $row.data('discount-percent', discountPercent);
            $row.data('discount-amount', discAmount);
            $row.data('line-total', lineTotal);

            $row.find('.item-total').text('₦' + money(lineTotal));
            if (qty > 0) {
                $row.css('background-color', 'rgba(24, 93, 48, 0.05)');
            } else {
                $row.css('background-color', '');
            }
        }

        // Recalculate all rows and totals
        function recalcGrand() {
            let grand = 0, disc = 0;
            $('#orderItemsBody tr').each(function(){
                const $row = $(this);
                const qty = parseFloat($row.find('.item-quantity').val()) || 0;
                if (qty > 0) {
                    recalcRow($row);
                    grand += parseFloat($row.data('line-total') || 0);
                    disc  += parseFloat($row.data('discount-amount') || 0);
                }
            });
            $('#grandTotalAmount').text(money(grand));
            if ($('#orderDiscountTotal').length) {
                $('#orderDiscountTotal').text(money(disc));
            }
            $('#summaryTotal').text('₦' + money(grand));
            return grand;
        }

        // Input events for quantities and NAIRA discounts
        $(document).on('input money:changed', '.item-quantity, .item-discount', function() {
            currentGrandTotal = recalcGrand();
            updatePaymentSummary();
        });

        // Payment method selection
        $('input[name="payment_method"]').on('change', function() {
            const paymentType = $(this).attr('data-type');
            const paymentValue = $(this).val();

            $('.payment-input-group').hide();
            $('.payment-split-input, #paymentBreakdownSection input, #cashAmount, #cardAmount, #transferAmount').val('');
            $('#paymentValidationMessage').hide();

            if (paymentType === 'single') {
                $('#paymentBreakdownSection').slideUp(300);
            } else if (paymentType === 'combo') {
                $('#paymentBreakdownSection').slideDown(400);

                setTimeout(function() {
                    if (paymentValue.indexOf('cash') !== -1) {
                        $('#cashInputGroup').show();
                    }
                    if (paymentValue.indexOf('card') !== -1) {
                        $('#cardInputGroup').show();
                    }
                    if (paymentValue.indexOf('transfer') !== -1) {
                        $('#transferInputGroup').show();
                    }
                    updatePaymentSummary();
                }, 100);
            }
        });

        // Helper to read a channel amount robustly
        function getChannelAmount(channel) {
            if (channel === 'cash') {
                const v = $('#cashAmount').val() || $('#cashInputGroup .payment-split-input').val() || $('#cashInputGroup input').first().val();
                return parseNum(v);
            }
            if (channel === 'card') {
                const v = $('#cardAmount').val() || $('#cardInputGroup .payment-split-input').val() || $('#cardInputGroup input').first().val();
                return parseNum(v);
            }
            if (channel === 'transfer') {
                const v = $('#transferAmount').val() || $('#transferInputGroup .payment-split-input').val() || $('#transferInputGroup input').first().val();
                return parseNum(v);
            }
            return 0;
        }

        // Update summary whenever money-masked inputs change
        $(document).on('money:changed input', '#paymentBreakdownSection input, .payment-split-input, #cashAmount, #cardAmount, #transferAmount', function() {
            updatePaymentSummary();
        });

        function updatePaymentSummary() {
            const totalText = $('#grandTotalAmount').text() || '0';
            const total = parseNum(totalText);
            const cashAmount = getChannelAmount('cash');
            const cardAmount = getChannelAmount('card');
            const transferAmount = getChannelAmount('transfer');
            const totalEntered = cashAmount + cardAmount + transferAmount;
            const balance = total - totalEntered;

            $('#summaryTotal').text('₦' + money(total));
            $('#summaryEntered').text('₦' + money(totalEntered));
            $('#summaryBalance').text('₦' + money(Math.abs(balance)));

            const $validationMsg = $('#paymentValidationMessage');
            const $balanceRow = $('#balanceRow');
            
            if ($('#paymentBreakdownSection').is(':visible')) {
                if (totalEntered === 0) {
                    $validationMsg.hide();
                    $balanceRow.removeClass('balanced excess insufficient');
                } else if (Math.abs(balance) < 0.01) {
                    $validationMsg
                        .removeClass('error warning')
                        .addClass('success')
                        .html('✓ Payment amounts match perfectly!')
                        .show();
                    $balanceRow.removeClass('excess insufficient').addClass('balanced');
                } else if (balance > 0) {
                    $validationMsg
                        .removeClass('success error')
                        .addClass('warning')
                        .html('⚠ Balance remaining: ₦' + money(balance))
                        .show();
                    $balanceRow.removeClass('balanced excess').addClass('insufficient');
                } else {
                    $validationMsg
                        .removeClass('success warning')
                        .addClass('error')
                        .html('✗ Amount exceeds total by: ₦' + money(Math.abs(balance)))
                        .show();
                    $balanceRow.removeClass('balanced insufficient').addClass('excess');
                }
            }
        }

        $('#clearOrderBtn').on('click', function() {
            if (confirm('Are you sure you want to clear all quantities?')) {
                $('.item-quantity').val(0);
                $('.item-discount').val(0);
                $('#orderItemsBody .item-total').text('₦0.00');
                $('#grandTotalAmount').text('0.00');
                if ($('#orderDiscountTotal').length) $('#orderDiscountTotal').text('0.00');
                $('.item-quantity').closest('tr').css('background-color', '');
                $('.payment-split-input, #paymentBreakdownSection input, #cashAmount, #cardAmount, #transferAmount').val('');
                updatePaymentSummary();
            }
        });

        // ✅ UPDATED ORDER FORM SUBMISSION WITH OFFLINE SUPPORT
        $('#orderForm').on('submit', function(e) {
            e.preventDefault();

            const orderItems = [];
            let totalDiscount = 0;

            $('#orderItemsBody tr').each(function() {
                const $row = $(this);
                const $qty = $row.find('.item-quantity');
                const qty = parseInt($qty.val()) || 0;
                if (qty <= 0) return;

                const price = parseFloat($qty.data('item-price')) || 0;
                const id = $qty.data('item-id');
                const name = $qty.data('item-name');

                recalcRow($row);

                const discP = parseFloat($row.data('discount-percent')) || 0;
                const discA = parseFloat($row.data('discount-amount')) || 0;
                const line  = parseFloat($row.data('line-total')) || (qty * price - discA);

                totalDiscount += discA;

                orderItems.push({
                    item_id: id,
                    item_name: name,
                    unit_price: price,
                    quantity: qty,
                    total: line,
                    discount_percent: discP,
                    discount_amount: discA
                });
            });

            if (orderItems.length === 0) {
                showNotification('Please add at least one item', 'error');
                return;
            }

            const paymentMethod = $('input[name="payment_method"]:checked').val();
            if (!paymentMethod) {
                showNotification('Please select a payment method', 'error');
                return;
            }

            const grandTotal = orderItems.reduce((s,i)=>s+(i.total||0),0);
            let paymentBreakdown = {};

            const paymentType = $('input[name="payment_method"]:checked').attr('data-type');
            if (paymentType === 'combo') {
                const cashAmount = getChannelAmount('cash');
                const cardAmount = getChannelAmount('card');
                const transferAmount = getChannelAmount('transfer');
                const totalEntered = cashAmount + cardAmount + transferAmount;

                if (totalEntered === 0) {
                    showNotification('Please enter payment amounts', 'error');
                    return;
                }

                if (Math.abs(totalEntered - grandTotal) > 0.01) {
                    const balance = grandTotal - totalEntered;
                    if (balance > 0) {
                        showNotification('Payment incomplete. Balance: ₦' + money(balance), 'error');
                    } else {
                        showNotification('Payment exceeds total by: ₦' + money(Math.abs(balance)), 'error');
                    }
                    return;
                }

                paymentBreakdown = {
                    cash: cashAmount,
                    card: cardAmount,
                    transfer: transferAmount
                };
            }

            const orderData = {
                action: 'capitito_ims_submit_order',
                nonce: capitito_ims_ajax.nonce,
                items: JSON.stringify(orderItems),
                payment_method: paymentMethod,
                payment_breakdown: JSON.stringify(paymentBreakdown),
                grand_total: grandTotal,
                total_discount: totalDiscount
            };

            $('#submitOrderBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Processing...');

            // ✅ CHECK IF ONLINE - IF OFFLINE, SAVE LOCALLY
            if (!navigator.onLine && typeof window.saveOrderOffline === 'function') {
                window.saveOrderOffline({
                    items: orderItems,
                    payment_method: paymentMethod,
                    payment_breakdown: paymentBreakdown,
                    grand_total: grandTotal,
                    total_discount: totalDiscount
                }).then(function() {
                    showNotification('✓ Order saved offline. Will sync when connection is restored.', 'success');
                    resetOrderForm();
                    $('#submitOrderBtn').prop('disabled', false).html('<span class="btn-icon">✓</span> Submit Order');
                }).catch(function(error) {
                    showNotification('Failed to save order offline: ' + error.message, 'error');
                    $('#submitOrderBtn').prop('disabled', false).html('<span class="btn-icon">✓</span> Submit Order');
                });
                return;
            }

            // Normal online submission
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: orderData,
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message || 'Order created successfully', 'success');
                        displayOrderConfirmation(response.data.order_id, response.data.receipt_number, orderItems, paymentMethod, paymentBreakdown, grandTotal, totalDiscount);
                        resetOrderForm();

                        // Trigger manual stock sync per item
                        orderItems.forEach(function(item) {
                            $.ajax({
                                url: capitito_ims_ajax.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'capitito_ims_force_sync_stock',
                                    nonce: capitito_ims_ajax.nonce,
                                    item_id: item.item_id
                                }
                            });
                        });

                        if ($('#orderHistoryTable').length) {
                            setTimeout(function() { loadOrderHistory(1); }, 800);
                        }
                    } else {
                        showNotification(response.data.message || 'Failed to create order', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX error:', error);
                    console.log('Response:', xhr.responseText);
                    
                    // ✅ IF NETWORK ERROR AND OFFLINE HANDLER AVAILABLE, SAVE OFFLINE
                    if (!navigator.onLine && typeof window.saveOrderOffline === 'function') {
                        window.saveOrderOffline({
                            items: orderItems,
                            payment_method: paymentMethod,
                            payment_breakdown: paymentBreakdown,
                            grand_total: grandTotal,
                            total_discount: totalDiscount
                        }).then(function() {
                            showNotification('✓ Order saved offline. Will sync when connection is restored.', 'success');
                            resetOrderForm();
                        }).catch(function(err) {
                            showNotification('Failed to save order: ' + err.message, 'error');
                        });
                    } else {
                        showNotification('An error occurred. Please try again.', 'error');
                    }
                },
                complete: function() {
                    $('#submitOrderBtn').prop('disabled', false).html('<span class="btn-icon">✓</span> Submit Order');
                }
            });
        });

        function resetOrderForm() {
            $('.item-quantity').val(0);
            $('.item-discount').val(0);
            $('#paymentBreakdownSection').hide();
            $('#paymentValidationMessage').hide();
            $('.payment-input-group').hide();
            $('.item-total').text('₦0.00');
            $('#grandTotalAmount').text('0.00');
            if ($('#orderDiscountTotal').length) $('#orderDiscountTotal').text('0.00');
            $('.item-quantity').closest('tr').css('background-color', '');
            $('#balanceRow').removeClass('balanced excess insufficient');
            $('input[name="payment_method"]').prop('checked', false);
            $('.payment-split-input, #paymentBreakdownSection input, #cashAmount, #cardAmount, #transferAmount').val('');
        }

        function displayOrderConfirmation(orderId, receiptNumber, items, paymentMethod, paymentBreakdown, grandTotal, totalDiscount) {
            const currentDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            const currentTime = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            // Store receipt data for printing
            window.setReceiptData({
                order_id: orderId,
                receipt_number: receiptNumber,
                items: items,
                payment_method: paymentMethod,
                payment_breakdown: paymentBreakdown,
                grand_total: grandTotal,
                total_discount: totalDiscount,
                staff_name: capitito_ims_ajax.user_name,
                created_at: new Date().toISOString()
            });

            let itemsHtml = '';
            items.forEach(item => {
                const discountInfo = item.discount_amount > 0 
                    ? `<div style="font-size: 12px; color: #D02223;">- ₦${money(item.discount_amount)}</div>`
                    : '';
                itemsHtml += `
                    <tr>
                        <td>${item.item_name}${discountInfo}</td>
                        <td style="text-align: center;">${item.quantity}</td>
                        <td style="text-align: right;">₦${money(item.unit_price)}</td>
                        <td style="text-align: right;"><strong>₦${money(item.total)}</strong></td>
                    </tr>
                `;
            });

            // Payment display (including combos)
            let paymentDisplayHtml = '';
            if (paymentBreakdown && Object.keys(paymentBreakdown).length > 0) {
                paymentDisplayHtml = '<div style="margin-top: 10px;">';
                if ((paymentBreakdown.cash||0) > 0) {
                    paymentDisplayHtml += `<p style="margin: 5px 0;">💵 Cash: <strong>₦${money(paymentBreakdown.cash)}</strong></p>`;
                }
                if ((paymentBreakdown.card||0) > 0) {
                    paymentDisplayHtml += `<p style="margin: 5px 0;">💳 Card: <strong>₦${money(paymentBreakdown.card)}</strong></p>`;
                }
                if ((paymentBreakdown.transfer||0) > 0) {
                    paymentDisplayHtml += `<p style="margin: 5px 0;">🏦 Transfer: <strong>₦${money(paymentBreakdown.transfer)}</strong></p>`;
                }
                paymentDisplayHtml += '</div>';
            } else {
                paymentDisplayHtml = `<p style="margin: 10px 0 0 0;">Method: <strong>${formatPaymentMethodLabel(paymentMethod)}</strong></p>`;
            }

            const discountHtml = totalDiscount > 0 
                ? `<p style="margin: 5px 0; color: #D02223;">💰 Total Discount: <strong>₦${money(totalDiscount)}</strong></p>`
                : '';

            // Display receipt number if available
            const receiptNumHtml = receiptNumber 
                ? `<div><strong>Receipt #:</strong> ${receiptNumber}</div>`
                : '';

            const confirmationHtml = `
                <div style="max-width: 800px; margin: 0 auto;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h1 style="color: #185D30; margin: 0;">CAPITITO</h1>
                        <p style="margin: 5px 0; color: #666;">Order Confirmation</p>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <div><strong>Order ID:</strong> #${orderId}</div>
                            <div><strong>Date:</strong> ${currentDate}</div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <div><strong>Staff:</strong> ${capitito_ims_ajax.user_name}</div>
                            <div><strong>Time:</strong> ${currentTime}</div>
                        </div>
                        ${receiptNumHtml ? `<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">${receiptNumHtml}</div>` : ''}
                    </div>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="background: #185D30; color: white;">
                                <th style="padding: 12px; text-align: left;">Item</th>
                                <th style="padding: 12px; text-align: center;">Qty</th>
                                <th style="padding: 12px; text-align: right;">Price</th>
                                <th style="padding: 12px; text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                    </table>
                    <div style="background: #185D30; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="margin: 0; font-weight: 600;">PAYMENT DETAILS</p>
                                ${paymentDisplayHtml}
                                ${discountHtml}
                            </div>
                            <div style="text-align: right;">
                                <p style="margin: 0;">GRAND TOTAL</p>
                                <p style="margin: 0; font-size: 32px; font-weight: 700;">₦${money(grandTotal)}</p>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: center; color: #666;">
                        <p style="font-weight: 600; margin-bottom: 10px;">Thank you for trusting CAPITITO!</p>
                    </div>
                </div>
            `;

            $('#orderConfirmationContent').html(confirmationHtml);
            openModal('orderConfirmationModal');
        }
    }

    // ========================================
    // ORDER HISTORY
    // ========================================
    function initializeOrderHistory() {
        if ($('#orderHistoryTable').length === 0) return;

        loadOrderHistory();

        $('#applyFiltersBtn').on('click', function() {
            currentPage = 1;
            loadOrderHistory();
        });

        $('#clearFiltersBtn').on('click', function() {
            $('#filterDateFrom, #filterDateTo, #filterPaymentMethod, #filterStaff').val('');
            currentPage = 1;
            loadOrderHistory();
        });

        $(document).on('click', '.view-order-btn', function() {
            const orderId = $(this).data('order-id');
            viewOrder(orderId);
        });

        $(document).on('click', '.edit-order-btn', function() {
            const orderId = $(this).data('order-id');
            editOrder(orderId);
        });

        $(document).on('click', '.delete-order-btn', function() {
            const orderId = $(this).data('order-id');
            if (confirm('Are you sure you want to delete this order?')) {
                deleteOrder(orderId);
            }
        });

        $('#editOrderForm').on('submit', function(e) {
            e.preventDefault();
            updateOrder();
        });
    }

    function loadOrderHistory(page = 1) {
        const filters = {
            date_from: $('#filterDateFrom').val(),
            date_to: $('#filterDateTo').val(),
            payment_method: $('#filterPaymentMethod').val(),
            staff: $('#filterStaff').val()
        };

        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_orders',
                nonce: capitito_ims_ajax.nonce,
                page: page,
                per_page: perPage,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    renderOrderHistory(response.data.orders || []);
                    renderPagination(response.data.total, page, '#paginationSection');
                }
            }
        });
    }

    function renderOrderHistory(orders) {
        const tbody = $('#orderHistoryBody');
        tbody.empty();

        if (orders.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="7" class="text-center" style="padding: 40px;">
                        <p style="color: #999; font-size: 16px;">No orders found.</p>
                    </td>
                </tr>
            `);
            return;
        }

        orders.forEach(order => {
            const orderDate = new Date(order.created_at);
            const dateStr = orderDate.toLocaleDateString();
            const timeStr = orderDate.toLocaleTimeString();

            const isAdmin = capitito_ims_ajax.is_admin;
            const actions = `
                <button class="btn btn-sm btn-secondary view-order-btn" data-order-id="${order.id}">
                    👁️ View
                </button>
                <button class="btn btn-sm btn-secondary print-order-btn" data-order-id="${order.id}">
                    🖨️ Print
                </button>
                ${isAdmin ? `
                    <button class="btn btn-sm btn-secondary edit-order-btn" data-order-id="${order.id}">
                        ✏️ Edit
                    </button>
                    <button class="btn btn-sm btn-danger delete-order-btn" data-order-id="${order.id}">
                        🗑️ Delete
                    </button>
                ` : ''}
            `;

            const methodLabel = formatPaymentMethodLabel(order.payment_method);
            const receiptNum = order.receipt_number ? `<br><small style="color:#666;">${order.receipt_number}</small>` : '';

            tbody.append(`
                <tr>
                    <td><strong>#${order.id}</strong>${receiptNum}</td>
                    <td>${dateStr}</td>
                    <td>${timeStr}</td>
                    <td>${order.staff_name}</td>
                    <td><span class="badge badge-info">${methodLabel}</span></td>
                    <td><strong>₦${parseFloat(order.grand_total).toFixed(2)}</strong></td>
                    <td>${actions}</td>
                </tr>
            `);
        });
    }

    function viewOrder(orderId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_order',
                nonce: capitito_ims_ajax.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    displayOrderDetails(response.data);
                }
            }
        });
    }

    function displayOrderDetails(order) {
        // Store order data for reprint functionality
        window.setHistoryOrderData(order);

        let itemsHtml = '';
        (order.items || []).forEach(item => {
            itemsHtml += `
                <tr>
                    <td>${item.item_name}</td>
                    <td>${item.quantity}</td>
                    <td>₦${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td><strong>₦${parseFloat(item.total).toFixed(2)}</strong></td>
                </tr>
            `;
        });

        let breakdown = order.payment_breakdown || order.breakdown || null;
        if (typeof breakdown === 'string') {
            try { breakdown = JSON.parse(breakdown); } catch(e) { breakdown = null; }
        }

        let paymentDetailsHtml = '';
        const prettyMethod = formatPaymentMethodLabel(order.payment_method);

        if (breakdown && (breakdown.cash || breakdown.card || breakdown.transfer)) {
            paymentDetailsHtml = `
                <div style="margin-top: 14px;">
                    <div style="font-weight:600; margin-bottom:6px;">Payment Method: ${prettyMethod}</div>
                    ${Number(breakdown.cash||0)     > 0 ? `<div>💵 Cash: <strong>₦${money(breakdown.cash)}</strong></div>` : ''}
                    ${Number(breakdown.card||0)     > 0 ? `<div>💳 Card: <strong>₦${money(breakdown.card)}</strong></div>` : ''}
                    ${Number(breakdown.transfer||0) > 0 ? `<div>🏦 Transfer: <strong>₦${money(breakdown.transfer)}</strong></div>` : ''}
                </div>
            `;
        } else {
            paymentDetailsHtml = `
                <div style="margin-top: 14px;">
                    <div style="font-weight:600;">Payment Method: ${prettyMethod}</div>
                </div>
            `;
        }

        const orderDate = new Date(order.created_at);
        const receiptNumHtml = order.receipt_number 
            ? `<p><strong>Receipt #:</strong> ${order.receipt_number}</p>` 
            : '';

        const detailsHtml = `
            <div class="order-details">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p><strong>Order ID:</strong> #${order.id}</p>
                    ${receiptNumHtml}
                    <p><strong>Date:</strong> ${orderDate.toLocaleDateString()}</p>
                    <p><strong>Time:</strong> ${orderDate.toLocaleTimeString()}</p>
                    <p><strong>Staff:</strong> ${order.staff_name}</p>
                    ${paymentDetailsHtml}
                </div>

                <table class="capitito-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                    <tfoot class="total-row">
                        <tr>
                            <td colspan="3"><strong>GRAND TOTAL</strong></td>
                            <td><strong>₦${parseFloat(order.grand_total).toFixed(2)}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;

        $('#viewOrderContent').html(detailsHtml);
        openModal('viewOrderModal');
    }

    function editOrder(orderId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_order',
                nonce: capitito_ims_ajax.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    $('#editOrderId').val(response.data.id);
                    $('#editPaymentMethod').val(response.data.payment_method);
                    $('#editGrandTotal').val(response.data.grand_total);
                    openModal('editOrderModal');
                }
            }
        });
    }

    function updateOrder() {
        const formData = {
            action: 'capitito_ims_update_order',
            nonce: capitito_ims_ajax.nonce,
            order_id: $('#editOrderId').val(),
            payment_method: $('#editPaymentMethod').val(),
            grand_total: $('#editGrandTotal').val()
        };

        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    closeModal('editOrderModal');
                    loadOrderHistory(currentPage);
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    }

    function deleteOrder(orderId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_delete_order',
                nonce: capitito_ims_ajax.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    loadOrderHistory(currentPage);
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    }

    // ========================================
    // PRODUCT SUMMARY
    // ========================================
    function initializeProductSummary() {
        if ($('#productSummaryTable').length === 0) return;

        loadProductSummary();

        $('#applyFiltersBtn').on('click', function() {
            currentPage = 1;
            loadProductSummary();
        });

        $('#clearFiltersBtn').on('click', function() {
            $('#filterDate').val(new Date().toISOString().split('T')[0]);
            $('#filterItem, #filterStaff').val('');
            currentPage = 1;
            loadProductSummary();
        });
    }

    function loadProductSummary(page = 1) {
        const filters = {
            date: $('#filterDate').val(),
            item: $('#filterItem').val(),
            staff: $('#filterStaff').val()
        };

        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_product_summary',
                nonce: capitito_ims_ajax.nonce,
                page: page,
                per_page: perPage,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    renderProductSummary(response.data.summary);
                    updateSummaryStats(response.data.stats);
                    renderPagination(response.data.total, page, '#paginationSection');
                }
            }
        });
    }

    function renderProductSummary(summary) {
        const tbody = $('#productSummaryBody');
        tbody.empty();

        if (summary.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="4" class="text-center" style="padding: 40px;">
                        <p style="color: #999; font-size: 16px;">No product summary found.</p>
                    </td>
                </tr>
            `);
            $('#footerTotalUnits').text(0);
            return;
        }

        let totalUnits = 0;

        summary.forEach(item => {
            totalUnits += parseInt(item.units_sold);
            tbody.append(`
                <tr>
                    <td><strong>${item.item_name}</strong></td>
                    <td>${item.units_sold}</td>
                    <td>${item.order_time}</td>
                    <td>${item.staff_name}</td>
                </tr>
            `);
        });

        $('#footerTotalUnits').text(totalUnits);
    }

    function updateSummaryStats(stats) {
        $('#totalProductsSold').text(stats.total_units || 0);
        $('#uniqueItems').text(stats.unique_items || 0);
        $('#totalOrders').text(stats.total_orders || 0);
    }

    // ========================================
    // FINANCIAL SUMMARY - with Money mask on fields and NAIRA-only discount details
    // ========================================
    function initializeFinancialSummary() {
        if ($('#financialSummaryForm').length === 0) return;

        attachMoneyMask('#totalSales, #card, #cash, #transfer, #expenses, #oldCash, #paidToBank, #cashLeft');

        // Auto-refresh Discount Today from server every 30s
        function refreshDiscountToday(){
            $.post(capitito_ims_ajax.ajax_url, {
                action: 'capitito_ims_get_daily_discount',
                nonce: capitito_ims_ajax.nonce,
                date: capitito_ims_ajax.current_date
            }).done(function(res){
                if (res && res.success) {
                    $('#discountToday').val('₦' + money(res.data.discount_total));
                }
            });
        }
        refreshDiscountToday();
        setInterval(refreshDiscountToday, 30000);

        // View discount details modal opener
        $(document).on('click', '#viewDiscountDetailsBtn', function(){
            $.post(capitito_ims_ajax.ajax_url, {
                action: 'capitito_ims_get_daily_discounts',
                nonce: capitito_ims_ajax.nonce,
                date: capitito_ims_ajax.current_date
            }).done(function(res){
                if (!(res && res.success)) return;

                const items = res.data.items || [];
                if (items.length === 0) {
                    $('#discountDetailsContent').html('<div style="padding:20px; text-align:center; color:#777;">No discounts recorded for today.</div>');
                } else {
                    let html = '<table class="capitito-table" style="width:100%;"><thead><tr>';
                    html += '<th>Item</th><th>Price</th><th>Qty</th><th>Discount (₦)</th></tr></thead><tbody>';
                    
                    items.forEach(it => {
                        html += '<tr>';
                        html += '<td><strong>' + escapeHtml(it.item_name || '') + '</strong></td>';
                        html += '<td>₦' + money(it.price) + '</td>';
                        html += '<td>' + Number(it.quantity) + '</td>';
                        html += '<td style="color:#D02223;"><strong>₦' + money(it.discount_amount) + '</strong></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    $('#discountDetailsContent').html(html);
                }
                
                $('#modalDiscountTotal').text(money(res.data.discount_total || 0));
                $('#discountDetailsModal').addClass('active');
            });

            function escapeHtml(s){
                return String(s||'').replace(/[&<>"']/g, m => ({
                    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
                }[m]));
            }
        });

        // When any money field changes, recalc cash left
        $(document).on('money:changed input', '#totalSales, #card, #cash, #transfer, #expenses, #oldCash, #paidToBank', function(){
            calculateCashLeft();
            validateCashLeft();
        });

        // Prevent negative values in inputs
        $('#expenses, #paidToBank').on('input', function() {
            const $input = $(this);
            let value = parseNum($input.val());
            
            if (value < 0) {
                $input.val(money(0));
                showNotification('❌ Negative values are not allowed!', 'error');
                return false;
            }
            
            calculateCashLeft();
            validateCashLeft();
        });

        // Block minus key
        $('#expenses, #paidToBank').on('keydown', function(e) {
            if (e.which === 189 || e.which === 109 || e.which === 173 || e.key === '-' || e.key === 'Minus') {
                e.preventDefault();
                return false;
            }
        });

        // Prevent paste of negative values
        $('#expenses, #paidToBank').on('paste', function() {
            setTimeout(() => {
                const $input = $(this);
                let value = parseNum($input.val());
                if (value < 0) {
                    $input.val(money(0));
                    showNotification('❌ Negative values are not allowed!', 'error');
                }
                calculateCashLeft();
                validateCashLeft();
            }, 10);
        });

        calculateCashLeft();
        validateCashLeft();

        $('#financialSummaryForm').on('submit', function(e) {
            e.preventDefault();

            const expenses = parseNum($('#expenses').val());
            const paidToBank = parseNum($('#paidToBank').val());
            const cashLeft = parseNum($('#cashLeft').val());

            if (expenses < 0) {
                showNotification('❌ Expenses cannot be negative!', 'error');
                return false;
            }

            if (paidToBank < 0) {
                showNotification('❌ Paid to Bank cannot be negative!', 'error');
                return false;
            }

            if (cashLeft < 0) {
                showNotification('❌ Cash Left cannot be negative! Please reduce Expenses or Paid to Bank.', 'error');
                $('#cashLeftWarning').slideDown(300);
                
                $('#expenses, #paidToBank').css({
                    'border-color': '#D02126',
                    'background': '#ffebee'
                });
                
                setTimeout(function() {
                    $('#expenses, #paidToBank').css({
                        'border-color': '',
                        'background': ''
                    });
                }, 3000);
                
                return false;
            }

            if (!confirm('Are you sure you want to submit this financial summary? This action cannot be undone.')) {
                return;
            }

            const formData = {
                action: 'capitito_ims_submit_financial',
                nonce: capitito_ims_ajax.nonce,
                expenses: expenses.toString(),
                expense_remark: $('#expenseRemark').val() || '',
                paid_to_bank: paidToBank.toString()
            };

            $('#submitFinancialBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Submitting...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification(response.data.message, 'error');
                        $('#submitFinancialBtn').prop('disabled', false).html('<span class="btn-icon">💾</span> Submit Financial Summary');
                    }
                },
                error: function() {
                    showNotification('An error occurred. Please try again.', 'error');
                    $('#submitFinancialBtn').prop('disabled', false).html('<span class="btn-icon">💾</span> Submit Financial Summary');
                }
            });
        });

        function validateCashLeft() {
            const cashLeft = parseNum($('#cashLeft').val());
            const $warning = $('#cashLeftWarning');
            const $submitBtn = $('#submitFinancialBtn');
            
            if (cashLeft < 0) {
                $warning.slideDown(300);
                $('#cashLeft').css({
                    'color': '#D02126',
                    'font-weight': '700',
                    'background': '#ffebee'
                });
                $submitBtn.prop('disabled', true).addClass('btn-disabled');
            } else {
                $warning.slideUp(300);
                $('#cashLeft').css({
                    'color': '',
                    'font-weight': '',
                    'background': ''
                });
                $submitBtn.prop('disabled', false).removeClass('btn-disabled');
            }
        }
    }

    function calculateCashLeft() {
        const totalSales = parseNum($('#totalSales').val());
        const card = parseNum($('#card').val());
        const transfer = parseNum($('#transfer').val());
        const expenses = parseNum($('#expenses').val());
        const oldCash = parseNum($('#oldCash').val());
        const paidToBank = parseNum($('#paidToBank').val());

        const cashLeft = totalSales - transfer - card - expenses + oldCash - paidToBank;
        $('#cashLeft').val(money(cashLeft));
    }

    // ========================================
    // FINANCIAL HISTORY
    // ========================================
    function initializeFinancialHistory() {
        if ($('#financialHistoryTable').length === 0) return;

        loadFinancialHistory();

        $('#applyFiltersBtn').on('click', function() {
            currentPage = 1;
            loadFinancialHistory();
        });

        $('#clearFiltersBtn').on('click', function() {
            $('#filterDateFrom, #filterDateTo, #filterSubmittedBy').val('');
            currentPage = 1;
            loadFinancialHistory();
        });
        
        // Prevent negative values in edit form
        $('#editTotalSales, #editCard, #editCash, #editTransfer, #editExpenses, #editOldCash, #editPaidToBank').on('input', function() {
            const $input = $(this);
            let value = parseFloat($input.val()) || 0;
            
            if (value < 0) {
                $input.val(0);
                showNotification('❌ Negative values are not allowed!', 'error');
                return false;
            }
            
            const totalSales = parseFloat($('#editTotalSales').val()) || 0;
            const card = parseFloat($('#editCard').val()) || 0;
            const transfer = parseFloat($('#editTransfer').val()) || 0;
            const expenses = parseFloat($('#editExpenses').val()) || 0;
            const oldCash = parseFloat($('#editOldCash').val()) || 0;
            const paidToBank = parseFloat($('#editPaidToBank').val()) || 0;

            const cashLeft = totalSales - transfer - card - expenses + oldCash - paidToBank;
            $('#editCashLeft').val(cashLeft.toFixed(2));
        });

        $(document).on('click', '.view-financial-btn', function() {
            const financialId = $(this).data('financial-id');
            viewFinancial(financialId);
        });

        $(document).on('click', '.edit-financial-btn', function() {
            const financialId = $(this).data('financial-id');
            editFinancial(financialId);
        });

        $(document).on('click', '.delete-financial-btn', function() {
            const financialId = $(this).data('financial-id');
            if (confirm('Are you sure you want to delete this financial record?')) {
                deleteFinancial(financialId);
            }
        });

        $('#editFinancialForm .form-control').on('input', function() {
            const totalSales = parseFloat($('#editTotalSales').val()) || 0;
            const card = parseFloat($('#editCard').val()) || 0;
            const transfer = parseFloat($('#editTransfer').val()) || 0;
            const expenses = parseFloat($('#editExpenses').val()) || 0;
            const oldCash = parseFloat($('#editOldCash').val()) || 0;
            const paidToBank = parseFloat($('#editPaidToBank').val()) || 0;

            const cashLeft = totalSales - transfer - card - expenses + oldCash - paidToBank;
            $('#editCashLeft').val(cashLeft.toFixed(2));
        });

        $('#editFinancialForm').on('submit', function(e) {
            e.preventDefault();
            updateFinancial();
        });
    }

    function loadFinancialHistory(page = 1) {
        const filters = {
            date_from: $('#filterDateFrom').val(),
            date_to: $('#filterDateTo').val(),
            submitted_by: $('#filterSubmittedBy').val()
        };

        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_financial_history',
                nonce: capitito_ims_ajax.nonce,
                page: page,
                per_page: perPage,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    renderFinancialHistory(response.data.history);
                    renderPagination(response.data.total, page, '#paginationSection');
                }
            }
        });
    }

    function renderFinancialHistory(history) {
        const tbody = $('#financialHistoryBody');
        tbody.empty();

        if (history.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="text-center" style="padding: 40px;">
                        <p style="color: #999; font-size: 16px;">No financial history found.</p>
                    </td>
                </tr>
            `);
            return;
        }

        history.forEach(record => {
            const isAdmin = capitito_ims_ajax.is_admin;
            const actions = `
                <button class="btn btn-sm btn-secondary view-financial-btn" data-financial-id="${record.id}">
                    👁️ View
                </button>
                ${isAdmin ? `
                    <button class="btn btn-sm btn-secondary edit-financial-btn" data-financial-id="${record.id}">
                        ✏️ Edit
                    </button>
                    <button class="btn btn-sm btn-danger delete-financial-btn" data-financial-id="${record.id}">
                        🗑️ Delete
                    </button>
                ` : ''}
            `;

            tbody.append(`
                <tr>
                    <td>${new Date(record.summary_date).toLocaleDateString()}</td>
                    <td><strong>₦${parseFloat(record.total_sales).toFixed(2)}</strong></td>
                    <td><strong>₦${parseFloat(record.cash_left).toFixed(2)}</strong></td>
                    <td>${record.submitted_by}</td>
                    <td>${new Date(record.created_at).toLocaleString()}</td>
                    <td>${actions}</td>
                </tr>
            `);
        });
    }

    function viewFinancial(financialId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_financial',
                nonce: capitito_ims_ajax.nonce,
                financial_id: financialId
            },
            success: function(response) {
                if (response.success) {
                    displayFinancialDetails(response.data);
                }
            }
        });
    }

    function displayFinancialDetails(financial) {
        const discountRow = financial.discount_total && parseFloat(financial.discount_total) > 0
            ? `<tr>
                <td><strong>Discount Total</strong></td>
                <td style="color: #D02223;">₦${parseFloat(financial.discount_total).toFixed(2)}</td>
               </tr>`
            : '';

        const detailsHtml = `
            <div class="financial-details">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p><strong>Date:</strong> ${new Date(financial.summary_date).toLocaleDateString()}</p>
                    <p><strong>Submitted By:</strong> ${financial.submitted_by}</p>
                    <p><strong>Submitted At:</strong> ${new Date(financial.created_at).toLocaleString()}</p>
                </div>

                <table class="capitito-table">
                    <tr>
                        <td><strong>Total Sales</strong></td>
                        <td>₦${parseFloat(financial.total_sales).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td><strong>Card</strong></td>
                        <td>₦${parseFloat(financial.card).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td><strong>Cash</strong></td>
                        <td>₦${parseFloat(financial.cash).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td><strong>Transfer</strong></td>
                        <td>₦${parseFloat(financial.transfer).toFixed(2)}</td>
                    </tr>
                    ${discountRow}
                    <tr>
                        <td><strong>Expenses</strong></td>
                        <td>₦${parseFloat(financial.expenses).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td><strong>Old Cash</strong></td>
                        <td>₦${parseFloat(financial.old_cash).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td><strong>Paid to Bank</strong></td>
                        <td>₦${parseFloat(financial.paid_to_bank).toFixed(2)}</td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Cash Left</strong></td>
                        <td><strong>₦${parseFloat(financial.cash_left).toFixed(2)}</strong></td>
                    </tr>
                </table>

                ${financial.expense_remark ? `
                    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px;">
                        <strong>Expense Remark:</strong>
                        <p style="margin: 10px 0 0 0;">${financial.expense_remark}</p>
                    </div>
                ` : ''}
            </div>
        `;

        $('#viewFinancialContent').html(detailsHtml);
        openModal('viewFinancialModal');
    }

    function editFinancial(financialId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
                        data: {
                action: 'capitito_ims_get_financial',
                nonce: capitito_ims_ajax.nonce,
                financial_id: financialId
            },
            success: function(response) {
                if (response.success) {
                    const f = response.data;
                    $('#editFinancialId').val(f.id);
                    $('#editTotalSales').val(f.total_sales);
                    $('#editCard').val(f.card);
                    $('#editCash').val(f.cash);
                    $('#editTransfer').val(f.transfer);
                    $('#editExpenses').val(f.expenses);
                    $('#editOldCash').val(f.old_cash);
                    $('#editPaidToBank').val(f.paid_to_bank);
                    $('#editCashLeft').val(f.cash_left);
                    $('#editExpenseRemark').val(f.expense_remark);
                    openModal('editFinancialModal');
                }
            }
        });
    }

    function updateFinancial() {
        const formData = {
            action: 'capitito_ims_update_financial',
            nonce: capitito_ims_ajax.nonce,
            financial_id: $('#editFinancialId').val(),
            total_sales: $('#editTotalSales').val(),
            card: $('#editCard').val(),
            cash: $('#editCash').val(),
            transfer: $('#editTransfer').val(),
            expenses: $('#editExpenses').val(),
            old_cash: $('#editOldCash').val(),
            paid_to_bank: $('#editPaidToBank').val(),
            expense_remark: $('#editExpenseRemark').val()
        };

        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    closeModal('editFinancialModal');
                    loadFinancialHistory(currentPage);
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    }

    function deleteFinancial(financialId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_delete_financial',
                nonce: capitito_ims_ajax.nonce,
                financial_id: financialId
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    loadFinancialHistory(currentPage);
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    }

    // ========================================
    // RECONCILIATION CALENDAR
    // ========================================
    function initializeReconciliation() {
        if ($('.reconciliation-calendar').length === 0) return;

        let currentDate = null;
        const isAdmin = capitito_ims_ajax.is_admin;
        const whatsappNumber = '2349019099708';

        $('#todayBtn').on('click', function() {
            window.location.href = window.location.pathname;
        });

        $('#applyFilterBtn').on('click', function() {
            const month = $('#filterMonth').val();
            const year = $('#filterYear').val();
            window.location.href = window.location.pathname + '?month=' + month + '&year=' + year;
        });

        $(document).on('click', '.btn-reconcile', function() {
            if (!isAdmin) {
                showNotification('⛔ Only administrators can reconcile dates', 'error');
                return;
            }

            const date = $(this).data('date');
            currentDate = date;
            
            const dateObj = new Date(date + 'T00:00:00');
            const formatted = dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            $('#reconcileDate').val(date);
            $('#reconcileDateDisplay').text(formatted);
            $('#reconcileNotes').val('');
            $('#reconcileModal').addClass('active');
        });

        $('#reconcileForm').on('submit', function(e) {
            e.preventDefault();

            const date = $('#reconcileDate').val();
            const notes = $('#reconcileNotes').val().trim();

            const $submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<span class="loading-spinner"></span> Processing...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_submit_reconciliation',
                    nonce: capitito_ims_ajax.nonce,
                    reconciliation_date: date,
                    notes: notes
                },
                success: function(response) {
                    if (response.success) {
                        $('#reconcileModal').removeClass('active');
                        showNotification(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification(response.data.message || 'Failed to reconcile', 'error');
                        $submitBtn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX Error:', error);
                    showNotification('An error occurred. Please try again.', 'error');
                    $submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        $(document).on('click', '.btn-submit-evidence', function() {
            const date = $(this).data('date');
            currentDate = date;
            
            const dateObj = new Date(date + 'T00:00:00');
            const formatted = dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            $('#evidenceDate').text(formatted);
            $('#submitEvidenceModal').addClass('active');
        });

        $('#openWhatsAppBtn').on('click', function() {
            const date = currentDate;
            
            if (!date) {
                showNotification('Error: No date selected', 'error');
                return;
            }

            const dateObj = new Date(date + 'T00:00:00');
            const dateStr = dateObj.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });

            const now = new Date();
            const timeStr = now.toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const message = 
                `*RECONCILIATION EVIDENCE SUBMISSION*\n\n` +
                `📅 Date: ${dateStr}\n` +
                `👤 Submitted by: ${capitito_ims_ajax.user_name}\n` +
                `⏰ Time: ${timeStr}\n\n` +
                `📎 Evidence files attached below.\n\n` +
                `_Capitito Inventory Management System_`;

            const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;

            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Opening...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_mark_evidence_sent',
                    nonce: capitito_ims_ajax.nonce,
                    reconciliation_date: date
                },
                success: function(response) {
                    if (response.success) {
                        window.open(whatsappUrl, '_blank');
                        showNotification('✅ Opening WhatsApp...', 'success');
                        setTimeout(function() {
                            $('#submitEvidenceModal').removeClass('active');
                            location.reload();
                        }, 2000);
                    } else {
                        showNotification(response.data.message || 'Failed to mark evidence', 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showNotification('⚠️ Opening WhatsApp anyway...', 'warning');
                    window.open(whatsappUrl, '_blank');
                    setTimeout(function() {
                        $('#submitEvidenceModal').removeClass('active');
                    }, 2000);
                }
            });
        });

        $(document).on('click', '.btn-view', function() {
            const date = $(this).data('date');
            
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_get_reconciliation_record_by_date',
                    nonce: capitito_ims_ajax.nonce,
                    reconciliation_date: date
                },
                success: function(response) {
                    if (response.success) {
                        const record = response.data;
                        
                        const dateObj = new Date(date + 'T00:00:00');
                        const formatted = dateObj.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        
                        $('#viewEvidenceDate').text(formatted);
                        
                        if (record.evidence_sent_at) {
                            try {
                                const evidenceDate = new Date(record.evidence_sent_at);
                                const evidenceStr = evidenceDate.toLocaleString('en-US', {
                                    month: 'long',
                                    day: 'numeric',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                                $('#evidenceSubmittedAt').text('Submitted on ' + evidenceStr);
                            } catch (e) {
                                $('#evidenceSubmittedAt').text('Evidence submitted');
                            }
                        }
                        
                        if (record.notes && record.notes.trim() !== '') {
                            $('#notesContent').text(record.notes);
                            $('#viewNotes').show();
                        } else {
                            $('#viewNotes').hide();
                        }
                        
                        $('#viewEvidenceModal').addClass('active');
                    } else {
                        showNotification('Failed to load details', 'error');
                    }
                },
                error: function() {
                    showNotification('Error loading details', 'error');
                }
            });
        });
    }

    // ========================================
    // RECONCILIATION HISTORY
    // ========================================
    function initializeReconciliationHistory() {
        if ($('#reconciliationHistoryTable').length === 0) return;

        let currentDateForEvidence = null;
        const whatsappNumber = '2349019099708';
        const isAdmin = capitito_ims_ajax.is_admin;

        loadReconciliationHistory();

        $('#applyFiltersBtn').on('click', function() {
            currentPage = 1;
            loadReconciliationHistory();
        });

        $('#clearFiltersBtn').on('click', function() {
            $('#filterDateFrom, #filterDateTo, #filterStaff').val('');
            $('#filterEvidence').val('');
            currentPage = 1;
            loadReconciliationHistory();
        });

        $(document).on('click', '.view-reconciliation-btn', function() {
            const recordId = $(this).data('record-id');
            viewReconciliationDetails(recordId);
        });

        $(document).on('click', '.submit-evidence-history-btn', function() {
            const date = $(this).data('date');
            currentDateForEvidence = date;
            
            const dateObj = new Date(date + 'T00:00:00');
            const formatted = dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            $('#evidenceDateHistory').text(formatted);
            $('#submitEvidenceHistoryModal').addClass('active');
        });

        $('#openWhatsAppHistoryBtn').on('click', function() {
            const date = currentDateForEvidence;
            
            if (!date) {
                showNotification('Error: No date selected', 'error');
                return;
            }

            const dateObj = new Date(date + 'T00:00:00');
            const dateStr = dateObj.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });

            const now = new Date();
            const timeStr = now.toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const message = 
                `*RECONCILIATION EVIDENCE SUBMISSION*\n\n` +
                `📅 Date: ${dateStr}\n` +
                `👤 Submitted by: ${capitito_ims_ajax.user_name}\n` +
                `⏰ Time: ${timeStr}\n\n` +
                `📎 Evidence files attached below.\n\n` +
                `_Capitito Inventory Management System_`;

            const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;

            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', false).html('<span class="loading-spinner"></span> Opening...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_mark_evidence_sent',
                    nonce: capitito_ims_ajax.nonce,
                    reconciliation_date: date
                },
                success: function(response) {
                    if (response.success) {
                        window.open(whatsappUrl, '_blank');
                        showNotification('✅ Opening WhatsApp...', 'success');
                        setTimeout(function() {
                            $('#submitEvidenceHistoryModal').removeClass('active');
                            loadReconciliationHistory(currentPage);
                        }, 2000);
                    } else {
                        showNotification(response.data.message, 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                }
            });
        });

        $(document).on('click', '.delete-reconciliation-btn', function() {
            if (!isAdmin) {
                showNotification('⛔ Only administrators can delete records', 'error');
                return;
            }

            const recordId = $(this).data('record-id');
            const date = $(this).data('date');
            
            if (!confirm(`⚠️ DELETE RECONCILIATION?\n\nDate: ${date}\n\nThis action CANNOT be undone!\n\nClick OK to delete.`)) {
                return;
            }
            
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_delete_reconciliation',
                    nonce: capitito_ims_ajax.nonce,
                    record_id: recordId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Deleted successfully', 'success');
                        setTimeout(function() {
                            loadReconciliationHistory(currentPage);
                        }, 800);
                    } else {
                        showNotification(response.data.message || 'Failed to delete', 'error');
                    }
                },
                error: function() {
                    showNotification('Error deleting record', 'error');
                }
            });
        });

        function loadReconciliationHistory(page = 1) {
            const filters = {
                date_from: $('#filterDateFrom').val(),
                date_to: $('#filterDateTo').val(),
                staff: $('#filterStaff').val(),
                evidence: $('#filterEvidence').val()
            };

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_get_reconciliation_history',
                    nonce: capitito_ims_ajax.nonce,
                    page: page,
                    per_page: perPage,
                    filters: filters
                },
                success: function(response) {
                    if (response.success) {
                        renderReconciliationHistory(response.data.history);
                        renderPagination(response.data.total, page, '#paginationSection');
                        $('#totalRecords').text(response.data.stats.total || 0);
                        $('#evidenceSent').text(response.data.stats.evidence_sent || 0);
                        $('#pendingEvidence').text(response.data.stats.pending_evidence || 0);
                    } else {
                        showNotification(response.data.message || 'Failed to load history', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX Error:', {xhr, status, error});
                    showNotification('Failed to load reconciliation history', 'error');
                    $('#reconciliationHistoryBody').html('<tr><td colspan="7" style="text-align: center; padding: 40px; color: #D02223;"><strong>Error loading data.</strong></td></tr>');
                }
            });
        }

        function renderReconciliationHistory(history) {
            const tbody = $('#reconciliationHistoryBody');
            tbody.empty();

            if (!history || history.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px;">
                            <div style="font-size: 48px; margin-bottom: 20px;">📋</div>
                            <p style="color: #999; font-size: 18px;">No reconciliation records found</p>
                        </td>
                    </tr>
                `);
                return;
            }

            history.forEach(record => {
                const reconDate = new Date(record.reconciliation_date);
                const formattedDate = reconDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

                const createdAt = new Date(record.created_at);
                const formattedCreated = createdAt.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

                const statusBadge = record.evidence_sent == 1 
                    ? '<span class="status-badge status-complete">✅ COMPLETE</span>'
                    : '<span class="status-badge status-pending">⚠️ PENDING</span>';

                const evidenceHtml = record.evidence_sent == 1 && record.evidence_sent_at
                    ? '<span class="evidence-sent">✓ Sent on ' + new Date(record.evidence_sent_at).toLocaleString('en-US', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'}) + '</span>'
                    : '<span class="evidence-pending">⚠️ Pending</span>';

                const notes = record.notes || '<em style="color: #999;">No notes</em>';
                const truncatedNotes = notes.length > 50 ? notes.substring(0, 50) + '...' : notes;

                let actions = `
                    <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                        <button class="btn btn-sm btn-secondary view-reconciliation-btn" data-record-id="${record.id}">
                            👁️ View
                        </button>`;

                if (record.evidence_sent == 0) {
                    actions += `
                        <button class="btn btn-sm btn-secondary submit-evidence-history-btn" data-date="${record.reconciliation_date}">
                            📱 Submit
                        </button>`;
                }

                if (isAdmin) {
                    actions += `
                        <button class="btn btn-sm btn-danger delete-reconciliation-btn" data-record-id="${record.id}" data-date="${formattedDate}">
                            🗑️
                        </button>`;
                }

                actions += `</div>`;

                tbody.append(`
                    <tr>
                        <td style="padding: 12px; border: 1px solid #ddd;"><strong>${formattedDate}</strong></td>
                        <td style="padding: 12px; border: 1px solid #ddd;">${record.reconciled_by}</td>
                        <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">${statusBadge}</td>
                        <td style="padding: 12px; border: 1px solid #ddd;">${truncatedNotes}</td>
                        <td style="padding: 12px; border: 1px solid #ddd;">${formattedCreated}</td>
                        <td style="padding: 12px; border: 1px solid #ddd;">${evidenceHtml}</td>
                        <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">${actions}</td>
                    </tr>
                `);
            });
        }

        function viewReconciliationDetails(recordId) {
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_get_reconciliation_record',
                    nonce: capitito_ims_ajax.nonce,
                    record_id: recordId
                },
                success: function(response) {
                    if (!response.success) {
                        showNotification('Failed to load details', 'error');
                        return;
                    }

                    const record = response.data;
                    const reconDate = new Date(record.reconciliation_date + 'T00:00:00');
                    const formattedReconDate = reconDate.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });

                    const createdAt = new Date(record.created_at);
                    const formattedCreated = createdAt.toLocaleString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });

                    let evidenceHtml = '';
                    if (record.evidence_sent == 1 && record.evidence_sent_at) {
                        const evidenceDate = new Date(record.evidence_sent_at);
                        const formattedEvidence = evidenceDate.toLocaleString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit'
                        });
                        evidenceHtml = `
                            <div style="background: linear-gradient(135deg, #d4edda, #c3e6cb); border-left: 5px solid #28a745; padding: 20px; border-radius: 8px;">
                                <strong style="color: #155724; font-size: 16px;">✓ Evidence Submitted</strong>
                                <p style="margin: 8px 0 0 0; color: #155724;">${formattedEvidence}</p>
                            </div>
                        `;
                    } else {
                        evidenceHtml = `
                            <div style="background: linear-gradient(135deg, #fff3cd, #ffeaa7); border-left: 5px solid #ffc107; padding: 20px; border-radius: 8px;">
                                <strong style="color: #856404; font-size: 16px;">⚠️ Evidence Pending</strong>
                                <p style="margin: 8px 0 0 0; color: #856404;">Evidence has not been submitted yet.</p>
                            </div>
                        `;
                    }

                    const detailsHtml = `
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: var(--primary-green);">Reconciliation Date</h4>
                            <p style="font-size: 20px; font-weight: 700; margin: 0; color: #333;">${formattedReconDate}</p>
                        </div>

                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600; width: 40%;">Reconciled By:</td>
                                <td style="padding: 12px;">${record.reconciled_by}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Created At:</td>
                                <td style="padding: 12px;">${formattedCreated}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Record ID:</td>
                                <td style="padding: 12px;">#${record.id}</td>
                            </tr>
                        </table>

                        ${evidenceHtml}

                        ${record.notes ? `
                            <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px;">
                                <strong>Notes:</strong>
                                <p style="margin: 10px 0 0 0;">${record.notes}</p>
                            </div>
                        ` : ''}
                    `;

                    $('#viewDetailsContent').html(detailsHtml);
                    $('#viewDetailsModal').addClass('active');
                }
            });
        }
    }
    
    // ========================================
    // STOCK PAGE - REAL-TIME AUTO-SYNC
    // ========================================
    function initializeStockPage() {
        if ($('#stockTable').length === 0) return;

        let updateTimeout;
        let syncInterval;

        startAutoSync();

        // Prevent negative values in stock inputs
        $(document).on('input', '.stock-input.editable', function() {
            const $input = $(this);
            let value = parseInt($input.val()) || 0;
            if (value < 0) {
                $input.val(0);
                showNotification('❌ Negative values are not allowed!', 'error');
                return false;
            }
        });

        // Prevent typing negative sign
        $(document).on('keydown', '.stock-input.editable', function(e) {
            if (e.which === 189 || e.which === 109 || e.which === 173) {
                e.preventDefault();
                return false;
            }
        });

        // Update stock on input change (with debounce)
        $(document).on('input', '.stock-input.editable', function() {
            const $input = $(this);
            const $row = $input.closest('tr');
            const stockId = $row.data('stock-id');
            const field = $input.data('field');
            const value = parseInt($input.val()) || 0;

            $row.addClass('saving');
            $row.find('.status-indicator').html('💾 Saving...').css('color', '#ff9800');

            clearTimeout(updateTimeout);
            updateTimeout = setTimeout(function() {
                updateStock(stockId, field, value, $row);
            }, 800);
        });

        function updateStock(stockId, field, value, $row) {
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_update_stock',
                    nonce: capitito_ims_ajax.nonce,
                    stock_id: stockId,
                    field: field,
                    value: value
                },
                success: function(response) {
                    if (response.success) {
                        const stock = response.data.stock;
                        $row.find('[data-field="opening"]').val(stock.opening);
                        $row.find('[data-field="imports"]').val(stock.imports);
                        $row.find('[data-field="sales"]').val(stock.sales);
                        $row.find('[data-field="hq_returned"]').val(stock.hq_returned);
                        $row.find('[data-field="closed"]').val(stock.closed);
                        $row.removeClass('saving');
                        $row.find('.status-indicator').html('✓ Saved').css('color', '#4caf50');
                        $row.css('background-color', '#e8f5e9');
                        setTimeout(function() {
                            $row.css('background-color', '');
                            $row.find('.status-indicator').html('✓ Synced').css('color', '#4caf50');
                        }, 1000);
                    } else {
                        $row.removeClass('saving');
                        $row.find('.status-indicator').html('❌ Error').css('color', '#D02223');
                        showNotification(response.data.message || 'Failed to update stock', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX Error:', error);
                    $row.removeClass('saving');
                    $row.find('.status-indicator').html('❌ Error').css('color', '#D02223');
                    showNotification('Failed to update stock', 'error');
                }
            });
        }

        function startAutoSync() {
            syncInterval = setInterval(function() {
                syncSalesFromOrders();
            }, 30000);
        }

        function syncSalesFromOrders() {
            $('#stockTable tbody tr').each(function() {
                const $row = $(this);
                const stockId = $row.data('stock-id');

                $.ajax({
                    url: capitito_ims_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'capitito_ims_sync_stock_sales',
                        nonce: capitito_ims_ajax.nonce,
                        stock_id: stockId
                    },
                    success: function(response) {
                        if (response.success && response.data.updated) {
                            const stock = response.data.stock;
                            $row.find('[data-field="sales"]').val(stock.sales);
                            $row.find('[data-field="closed"]').val(stock.closed);
                        }
                    }
                });
            });
        }

        $(document).on('click', '#syncSalesBtn', function() {
            showNotification('🔄 Syncing sales...', 'info');
            syncSalesFromOrders();
        });

        $(window).on('beforeunload', function() {
            clearInterval(syncInterval);
        });
    }

    // ========================================
    // STOCK HISTORY
    // ========================================
    function initializeStockHistory() {
        if ($('#stockHistoryTable').length === 0) return;

        loadStockHistory();

        $('#applyFiltersBtn').on('click', function() {
            currentPage = 1;
            loadStockHistory();
        });

        $('#clearFiltersBtn').on('click', function() {
            $('#filterDateFrom').val('');
            $('#filterDateTo').val('');
            $('#filterItem').val('');
            currentPage = 1;
            loadStockHistory();
        });

        $(document).on('click', '.edit-stock-btn', function() {
            const stockId = $(this).data('stock-id');
            editStockRecord(stockId);
        });

        $(document).on('click', '.delete-stock-btn', function() {
            const stockId = $(this).data('stock-id');
            if (confirm('⚠️ Are you sure you want to delete this stock record?\n\nThis action cannot be undone!')) {
                deleteStockRecord(stockId);
            }
        });

        $('#editOpening, #editImports, #editHqReturned').on('input', function() {
            const opening = parseInt($('#editOpening').val()) || 0;
            const imports = parseInt($('#editImports').val()) || 0;
            const sales = parseInt($('#editSales').val()) || 0;
            const hqReturned = parseInt($('#editHqReturned').val()) || 0;
            const closed = opening + imports - sales - hqReturned;
            $('#editClosed').val(closed);
        });

        $('#editStockForm').on('submit', function(e) {
            e.preventDefault();
            updateStockRecord();
        });
    }

    function loadStockHistory(page = 1) {
        const filters = {
            date_from: $('#filterDateFrom').val(),
            date_to: $('#filterDateTo').val(),
            item: $('#filterItem').val()
        };

        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_stock_history',
                nonce: capitito_ims_ajax.nonce,
                page: page,
                per_page: perPage,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    renderStockHistory(response.data.history);
                    renderPagination(response.data.total, page, '#paginationSection');
                    $('#totalRecords').text(response.data.total);
                    
                    if (filters.date_from && filters.date_to) {
                        const from = new Date(filters.date_from).toLocaleDateString();
                        const to = new Date(filters.date_to).toLocaleDateString();
                        $('#dateRange').text(from + ' - ' + to);
                    } else {
                        $('#dateRange').text('All Dates');
                    }
                } else {
                    showNotification(response.data.message || 'Failed to load stock history', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', {xhr, status, error});
                showNotification('Failed to load stock history', 'error');
                $('#stockHistoryBody').html('<tr><td colspan="8" style="text-align: center; padding: 40px; color: #D02223;"><strong>Error loading data. Please refresh the page.</strong></td></tr>');
            }
        });
    }

    function renderStockHistory(history) {
        const tbody = $('#stockHistoryBody');
        tbody.empty();

        const isAdmin = capitito_ims_ajax.is_admin;
        const colspan = isAdmin ? '8' : '7';

        if (!history || history.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="${colspan}" style="text-align: center; padding: 60px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                        <p style="color: #999; font-size: 18px;">No stock history found</p>
                        <p style="color: #666;">Try adjusting your filters or check back later</p>
                    </td>
                </tr>
            `);
            return;
        }

        history.forEach(record => {
            const date = new Date(record.stock_date);
            const formattedDate = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

            const actions = isAdmin ? `
                <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">
                    <button class="btn btn-sm btn-secondary edit-stock-btn" data-stock-id="${record.id}">
                        ✏️ Edit
                    </button>
                    <button class="btn btn-sm btn-danger delete-stock-btn" data-stock-id="${record.id}">
                        🗑️
                    </button>
                </td>
            ` : '';

            tbody.append(`
                <tr>
                    <td style="padding: 12px; border: 1px solid #ddd;">${formattedDate}</td>
                    <td style="padding: 12px; border: 1px solid #ddd;"><strong>${record.item_name}</strong></td>
                    <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">${record.opening || 0}</td>
                    <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">${record.imports || 0}</td>
                    <td style="padding: 12px; text-align: center; border: 1px solid #ddd; background: #fff3e0; font-weight: 700;">${record.sales || 0}</td>
                    <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">${record.hq_returned || 0}</td>
                    <td style="padding: 12px; text-align: center; border: 1px solid #ddd; background: #e8f5e9; font-weight: 700; color: #2e7d32;">${record.closed || 0}</td>
                    ${actions}
                </tr>
            `);
        });
    }

    function editStockRecord(stockId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_stock',
                nonce: capitito_ims_ajax.nonce,
                stock_id: stockId
            },
            success: function(response) {
                if (response.success) {
                    const s = response.data;
                    $('#editStockId').val(s.id);
                    $('#editStockDate').val(s.stock_date);
                    $('#editItemName').val(s.item_name);
                    $('#editOpening').val(s.opening || 0);
                    $('#editImports').val(s.imports || 0);
                    $('#editSales').val(s.sales || 0);
                    $('#editHqReturned').val(s.hq_returned || 0);
                    $('#editClosed').val(s.closed || 0);
                    $('#editStockModal').addClass('active');
                } else {
                    showNotification('Failed to load stock record', 'error');
                }
            },
            error: function() {
                showNotification('Error loading stock record', 'error');
            }
        });
    }

    function updateStockRecord() {
        const formData = {
            action: 'capitito_ims_update_stock_history',
            nonce: capitito_ims_ajax.nonce,
            stock_id: $('#editStockId').val(),
            opening: $('#editOpening').val(),
            imports: $('#editImports').val(),
            hq_returned: $('#editHqReturned').val()
        };

        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('✓ Stock updated successfully', 'success');
                    $('#editStockModal').removeClass('active');
                    loadStockHistory(currentPage);
                } else {
                    showNotification(response.data.message || 'Failed to update stock', 'error');
                }
            },
            error: function() {
                showNotification('Error updating stock', 'error');
            }
        });
    }

    function deleteStockRecord(stockId) {
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_delete_stock_history',
                nonce: capitito_ims_ajax.nonce,
                stock_id: stockId
            },
            success: function(response) {
                if (response.success) {
                    showNotification('✓ Stock record deleted', 'success');
                    loadStockHistory(currentPage);
                } else {
                    showNotification(response.data.message || 'Failed to delete', 'error');
                }
            },
            error: function() {
                showNotification('Error deleting record', 'error');
            }
        });
    }
    
    // ========================================
    // HEALTH DASHBOARD
    // ========================================
    function initializeHealthDashboard() {
        if ($('#runHealthCheckBtn').length === 0) return;

        $('#runHealthCheckBtn').on('click', function() {
            const $btn = $(this);
            const originalHtml = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Running checks...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_run_health_check',
                    nonce: capitito_ims_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Health check completed', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Failed to run health check', 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showNotification('Error running health check', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        $('#forceDatabaseSyncBtn').on('click', function() {
            if (!confirm('Force database sync? This will add missing columns and indexes.')) {
                return;
            }

            const $btn = $(this);
            const originalHtml = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Syncing...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_force_database_sync',
                    nonce: capitito_ims_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Database synced successfully', 'success');
                        if (response.data.results && response.data.results.length > 0) {
                            alert('Changes made:\n\n' + response.data.results.join('\n'));
                        }
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification('Failed to sync database', 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showNotification('Error syncing database', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        $('#optimizeTablesBtn').on('click', function() {
            if (!confirm('Optimize all database tables? This may take a few moments.')) {
                return;
            }

            const $btn = $(this);
            const originalHtml = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Optimizing...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_optimize_tables',
                    nonce: capitito_ims_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Tables optimized successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification('Failed to optimize tables', 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showNotification('Error optimizing tables', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        $('#createBackupBtn').on('click', function() {
            const $btn = $(this);
            const originalHtml = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Creating backup...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_create_backup',
                    nonce: capitito_ims_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Backup created successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Failed to create backup', 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showNotification('Error creating backup', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        $('#clearCachesBtn').on('click', function() {
            if (!confirm('Clear all caches and transients?')) {
                return;
            }

            const $btn = $(this);
            const originalHtml = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Clearing...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_clear_caches',
                    nonce: capitito_ims_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Caches cleared successfully', 'success');
                        $btn.prop('disabled', false).html(originalHtml);
                    } else {
                        showNotification('Failed to clear caches', 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function() {
                    showNotification('Error clearing caches', 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        $(document).on('click', '.download-backup-btn', function() {
            const filename = $(this).data('filename');
            window.location.href = capitito_ims_ajax.ajax_url + '?action=capitito_ims_download_backup&nonce=' + capitito_ims_ajax.nonce + '&filename=' + filename;
        });

        $(document).on('click', '.restore-backup-btn', function() {
            const filename = $(this).data('filename');
            
            if (!confirm('⚠️ RESTORE FROM BACKUP?\n\nThis will replace ALL current data with the backup data.\n\nAre you absolutely sure?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Restoring...');

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_restore_backup',
                    nonce: capitito_ims_ajax.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Backup restored successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotification(response.data.message || 'Failed to restore backup', 'error');
                        $btn.prop('disabled', false).html('↩️ Restore');
                    }
                },
                error: function() {
                    showNotification('Error restoring backup', 'error');
                    $btn.prop('disabled', false).html('↩️ Restore');
                }
            });
        });

        $(document).on('click', '.delete-backup-btn', function() {
            const filename = $(this).data('filename');
            
            if (!confirm('Delete this backup? This action cannot be undone.')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_delete_backup',
                    nonce: capitito_ims_ajax.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Backup deleted', 'success');
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        showNotification('Failed to delete backup', 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    showNotification('Error deleting backup', 'error');
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    // ========================================
    // ADMIN PANEL - WITH HIDE/UNHIDE
    // ========================================
    function initializeAdminPanel() {
        if ($('#adminItemsTable').length === 0) return;

        $('#addNewItemBtn').on('click', function() {
            openModal('addItemModal');
        });

        $('#addItemForm').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                action: 'capitito_ims_add_item',
                nonce: capitito_ims_ajax.nonce,
                name: $('#addItemName').val(),
                price: $('#addItemPrice').val()
            };

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        closeModal('addItemModal');
                        $('#addItemForm')[0].reset();
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification(response.data.message, 'error');
                    }
                }
            });
        });

        $(document).on('click', '.edit-item-btn', function() {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');
            const itemPrice = $(this).data('item-price');

            $('#editItemId').val(itemId);
            $('#editItemName').val(itemName);
            $('#editItemPrice').val(itemPrice);
            openModal('editItemModal');
        });

        $('#editItemForm').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                action: 'capitito_ims_update_item',
                nonce: capitito_ims_ajax.nonce,
                item_id: $('#editItemId').val(),
                name: $('#editItemName').val(),
                price: $('#editItemPrice').val()
            };

            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        closeModal('editItemModal');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification(response.data.message, 'error');
                    }
                }
            });
        });

        $(document).on('click', '.hide-item-btn', function() {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');

            if (!itemId) {
                showNotification('Item ID not found', 'error');
                return;
            }

            if (!confirm(`Hide "${itemName}"?\n\nThis item will be hidden from Orders and Stock but can be unhidden later.`)) {
                return;
            }

            hideItem(itemId);
        });

        $(document).on('click', '.unhide-item-btn', function() {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');

            if (!itemId) {
                showNotification('Item ID not found', 'error');
                return;
            }

            if (!confirm(`Unhide "${itemName}"?\n\nThis item will appear again in Orders and Stock.`)) {
                return;
            }

            unhideItem(itemId);
        });

        $(document).on('click', '.delete-item-btn', function() {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');

            if (!itemId) {
                showNotification('Item ID not found', 'error');
                return;
            }

            if (!confirm(`⚠️ PERMANENTLY DELETE "${itemName}"?\n\nThis action CANNOT be undone!\n\nClick OK to delete.`)) {
                return;
            }

            deleteItem(itemId);
        });

        function hideItem(itemId) {
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_archive_item',
                    nonce: capitito_ims_ajax.nonce,
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Item hidden successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification(response.data.message || 'Failed to hide item', 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to hide item', 'error');
                }
            });
        }

        function unhideItem(itemId) {
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_unarchive_item',
                    nonce: capitito_ims_ajax.nonce,
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Item unhidden successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification(response.data.message || 'Failed to unhide item', 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to unhide item', 'error');
                }
            });
        }

        function deleteItem(itemId) {
            $.ajax({
                url: capitito_ims_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'capitito_ims_delete_item',
                    nonce: capitito_ims_ajax.nonce,
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('✓ Item deleted successfully', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification(response.data.message || 'Failed to delete item', 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to delete item', 'error');
                }
            });
        }
    }

    // ========================================
    // PAGINATION
    // ========================================
    function renderPagination(total, currentPage, containerSelector) {
        const totalPages = Math.ceil(total / perPage);
        
        if (totalPages <= 1) {
            $(containerSelector).empty();
            return;
        }

        let html = '<div class="pagination-section">';
        
        html += `<button class="pagination-btn" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>← Previous</button>`;
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span class="pagination-info">...</span>';
            }
        }
        
        html += `<button class="pagination-btn" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>Next →</button>`;
        
        html += '</div>';
        
        $(containerSelector).html(html);

        $(containerSelector).off('click', '.pagination-btn').on('click', '.pagination-btn', function() {
            if ($(this).prop('disabled')) return;
            
            const page = parseInt($(this).data('page'));
            currentPage = page;
            
            if ($('#orderHistoryTable').length) {
                loadOrderHistory(page);
            } else if ($('#productSummaryTable').length) {
                loadProductSummary(page);
            } else if ($('#financialHistoryTable').length) {
                loadFinancialHistory(page);
            } else if ($('#stockHistoryTable').length) {
                loadStockHistory(page); 
            } else if ($('#reconciliationHistoryTable').length) {
                loadReconciliationHistory(page);
            }
        });
    }

    // ========================================
    // NOTIFICATIONS
    // ========================================
    function showNotification(message, type = 'info') {
        $('.capitito-notification').remove();

        const bgColor = type === 'success' ? '#28a745' : type === 'error' ? '#D02126' : type === 'warning' ? '#ff9800' : '#2196F3';
        
        const notification = $(`
            <div class="capitito-notification" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: ${bgColor};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 99999;
                min-width: 300px;
                animation: slideInRight 0.3s ease;
            ">
                <strong>${message}</strong>
            </div>
        `);

        $('body').append(notification);

        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    // ========================================
    // BLUETOOTH THERMAL PRINTER SUPPORT
    // ========================================
    
    // Store current order data for printing
    let currentReceiptData = null;
    
    // ESC/POS Commands for thermal printers
    const ESC_POS = {
        INIT: '\x1B\x40',                    // Initialize printer
        ALIGN_CENTER: '\x1B\x61\x01',        // Center alignment
        ALIGN_LEFT: '\x1B\x61\x00',          // Left alignment
        ALIGN_RIGHT: '\x1B\x61\x02',         // Right alignment
        BOLD_ON: '\x1B\x45\x01',             // Bold on
        BOLD_OFF: '\x1B\x45\x00',            // Bold off
        DOUBLE_HEIGHT_ON: '\x1B\x21\x10',    // Double height on
        DOUBLE_WIDTH_ON: '\x1B\x21\x20',     // Double width on
        DOUBLE_ON: '\x1B\x21\x30',           // Double height and width
        NORMAL_SIZE: '\x1B\x21\x00',         // Normal size
        FEED: '\x1B\x64\x02',                // Feed 2 lines
        CUT: '\x1D\x56\x00',                 // Full cut
        PARTIAL_CUT: '\x1D\x56\x01',         // Partial cut
        LINE: '------------------------------------------------\n'
    };
    
    // Character width for 80mm paper (approximately 48 characters)
    const RECEIPT_WIDTH = 48;
    
    /**
     * Format text to fixed width for receipt
     */
    function padText(text, width, align = 'left') {
        text = String(text || '');
        if (text.length >= width) {
            return text.substring(0, width);
        }
        const padding = width - text.length;
        if (align === 'center') {
            const leftPad = Math.floor(padding / 2);
            const rightPad = padding - leftPad;
            return ' '.repeat(leftPad) + text + ' '.repeat(rightPad);
        } else if (align === 'right') {
            return ' '.repeat(padding) + text;
        }
        return text + ' '.repeat(padding);
    }
    
    /**
     * Format currency for receipt (browser/download version with Naira symbol)
     */
    function formatReceiptMoney(amount) {
        return 'N' + Number(amount || 0).toLocaleString('en-US', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    }
    
    /**
     * Format currency for ESC/POS thermal printer (ASCII-safe)
     * Uses "N" prefix with commas for readability
     */
    function formatPrinterMoney(amount) {
        return 'N' + Number(amount || 0).toLocaleString('en-US', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    }
    
    /**
     * Format payment method for display
     * Converts payment method codes to readable format
     */
    function formatPaymentMethod(method) {
        if (!method) return 'N/A';
        
        const methodStr = String(method).toLowerCase();
        
        // Handle combo payments
        if (methodStr.includes('_')) {
            return methodStr.split('_').map(m => {
                return m.charAt(0).toUpperCase() + m.slice(1);
            }).join(' + ');
        }
        
        // Single payment methods
        const methods = {
            'cash': 'CASH',
            'card': 'CARD (POS)',
            'transfer': 'BANK TRANSFER',
            'pos': 'CARD (POS)'
        };
        
        return methods[methodStr] || methodStr.toUpperCase();
    }
    
    /**
     * Generate text-based receipt for 80mm thermal printer
     * With improved formatting, spacing, and readability
     */
    function generateTextReceipt(data, isReprint = false) {
        const line = '='.repeat(RECEIPT_WIDTH);
        const dashLine = '-'.repeat(RECEIPT_WIDTH);
        
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-GB', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric' 
        });
        const timeStr = now.toLocaleTimeString('en-GB', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
        
        // Parse payment breakdown if it's a string
        let paymentBreakdown = data.payment_breakdown;
        if (typeof paymentBreakdown === 'string' && paymentBreakdown) {
            try { paymentBreakdown = JSON.parse(paymentBreakdown); } catch(e) { paymentBreakdown = null; }
        }
        
        let receipt = '';
        
        // Header
        receipt += line + '\n';
        receipt += padText('CAPITITO STORE', RECEIPT_WIDTH, 'center') + '\n';
        receipt += '\n';
        receipt += padText(isReprint ? '*** RECEIPT (REPRINT) ***' : '*** RECEIPT ***', RECEIPT_WIDTH, 'center') + '\n';
        receipt += line + '\n';
        receipt += '\n';
        
        // Receipt info with clear labels
        receipt += 'Receipt #: ' + (data.receipt_number || 'N/A') + '\n';
        receipt += '\n';
        receipt += 'Date: ' + dateStr + '        Time: ' + timeStr + '\n';
        receipt += '\n';
        receipt += 'Staff: ' + (data.staff_name || capitito_ims_ajax.user_name || 'Staff') + '\n';
        receipt += dashLine + '\n';
        receipt += '\n';
        
        // Items header
        const itemCol = 18;
        const qtyCol = 5;
        const priceCol = 12;
        const totalCol = 13;
        
        receipt += padText('ITEM', itemCol) + 
                   padText('QTY', qtyCol, 'center') + 
                   padText('PRICE', priceCol, 'right') + 
                   padText('AMOUNT', totalCol, 'right') + '\n';
        receipt += dashLine + '\n';
        
        // Items
        let subtotal = 0;
        let totalDiscount = 0;
        
        (data.items || []).forEach(item => {
            const itemName = (item.item_name || item.name || 'Item').substring(0, itemCol);
            const qty = item.quantity || 0;
            const price = parseFloat(item.unit_price || item.price || 0);
            const itemTotal = parseFloat(item.total || (qty * price));
            const discount = parseFloat(item.discount_amount || 0);
            
            subtotal += itemTotal + discount;
            totalDiscount += discount;
            
            receipt += padText(itemName, itemCol) + 
                       padText(qty.toString(), qtyCol, 'center') + 
                       padText(formatReceiptMoney(price), priceCol, 'right') + 
                       padText(formatReceiptMoney(itemTotal), totalCol, 'right') + '\n';
            
            // Show discount if applicable
            if (discount > 0) {
                receipt += padText('  Discount:', itemCol + qtyCol) + 
                           padText('-' + formatReceiptMoney(discount), priceCol + totalCol, 'right') + '\n';
            }
        });
        
        receipt += '\n';
        receipt += dashLine + '\n';
        receipt += '\n';
        
        // Totals
        const labelWidth = 34;
        const valueWidth = 14;
        
        if (totalDiscount > 0) {
            receipt += padText('Subtotal:', labelWidth, 'right') + 
                       padText(formatReceiptMoney(subtotal), valueWidth, 'right') + '\n';
            receipt += padText('Discount:', labelWidth, 'right') + 
                       padText('-' + formatReceiptMoney(totalDiscount), valueWidth, 'right') + '\n';
            receipt += padText('', labelWidth, 'right') + 
                       padText('-'.repeat(valueWidth), valueWidth) + '\n';
        }
        
        receipt += '\n';
        receipt += padText('*** GRAND TOTAL ***', labelWidth, 'right') + 
                   padText(formatReceiptMoney(data.grand_total || (subtotal - totalDiscount)), valueWidth, 'right') + '\n';
        receipt += '\n';
        receipt += dashLine + '\n';
        receipt += '\n';
        
        // Payment method - formatted properly
        receipt += 'PAYMENT METHOD: ';
        if (paymentBreakdown && typeof paymentBreakdown === 'object' && (paymentBreakdown.cash > 0 || paymentBreakdown.card > 0 || paymentBreakdown.transfer > 0)) {
            const parts = [];
            if (paymentBreakdown.cash > 0) {
                parts.push('Cash (' + formatReceiptMoney(paymentBreakdown.cash) + ')');
            }
            if (paymentBreakdown.card > 0) {
                parts.push('Card (' + formatReceiptMoney(paymentBreakdown.card) + ')');
            }
            if (paymentBreakdown.transfer > 0) {
                parts.push('Transfer (' + formatReceiptMoney(paymentBreakdown.transfer) + ')');
            }
            receipt += parts.join(' + ');
        } else if (data.payment_method) {
            receipt += formatPaymentMethod(data.payment_method);
        } else {
            receipt += 'N/A';
        }
        receipt += '\n';
        receipt += '\n';
        
        // Footer
        receipt += line + '\n';
        receipt += padText('Thank you for your purchase!', RECEIPT_WIDTH, 'center') + '\n';
        receipt += padText('Please come again', RECEIPT_WIDTH, 'center') + '\n';
        receipt += line + '\n';
        receipt += '\n\n\n';
        
        return receipt;
    }
    
    /**
     * Generate ESC/POS formatted receipt for thermal printer
     * Uses ASCII-safe currency format with proper spacing and bold amounts
     */
    function generateEscPosReceipt(data, isReprint = false) {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-GB');
        const timeStr = now.toLocaleTimeString('en-GB');
        
        // Parse payment breakdown if it's a string
        let paymentBreakdown = data.payment_breakdown;
        if (typeof paymentBreakdown === 'string' && paymentBreakdown) {
            try { paymentBreakdown = JSON.parse(paymentBreakdown); } catch(e) { paymentBreakdown = null; }
        }
        
        let receipt = ESC_POS.INIT;
        
        // Header - centered and prominent
        receipt += ESC_POS.ALIGN_CENTER;
        receipt += ESC_POS.DOUBLE_ON;
        receipt += 'CAPITITO STORE\n';
        receipt += ESC_POS.NORMAL_SIZE;
        receipt += '\n';
        receipt += isReprint ? '*** RECEIPT (REPRINT) ***\n' : '*** RECEIPT ***\n';
        receipt += ESC_POS.LINE;
        receipt += '\n';
        
        // Receipt info - left aligned with clear spacing
        receipt += ESC_POS.ALIGN_LEFT;
        receipt += ESC_POS.BOLD_ON;
        receipt += 'Receipt #: ';
        receipt += ESC_POS.BOLD_OFF;
        receipt += (data.receipt_number || 'N/A') + '\n';
        receipt += '\n';
        receipt += ESC_POS.BOLD_ON;
        receipt += 'Date: ';
        receipt += ESC_POS.BOLD_OFF;
        receipt += dateStr + '    ';
        receipt += ESC_POS.BOLD_ON;
        receipt += 'Time: ';
        receipt += ESC_POS.BOLD_OFF;
        receipt += timeStr + '\n';
        receipt += '\n';
        receipt += ESC_POS.BOLD_ON;
        receipt += 'Staff: ';
        receipt += ESC_POS.BOLD_OFF;
        receipt += (data.staff_name || capitito_ims_ajax.user_name || 'Staff') + '\n';
        receipt += ESC_POS.LINE;
        receipt += '\n';
        
        // Items header - bold
        receipt += ESC_POS.BOLD_ON;
        receipt += 'ITEM            QTY   PRICE     AMOUNT\n';
        receipt += ESC_POS.BOLD_OFF;
        receipt += ESC_POS.LINE;
        
        // Items list with bold amounts
        let grandTotal = 0;
        let totalDiscount = 0;
        (data.items || []).forEach(item => {
            const name = (item.item_name || item.name || 'Item').substring(0, 14);
            const qty = item.quantity || 0;
            const price = parseFloat(item.unit_price || item.price || 0);
            const total = parseFloat(item.total || (qty * price));
            const discount = parseFloat(item.discount_amount || 0);
            grandTotal += total;
            totalDiscount += discount;
            
            // Item name and quantity (normal)
            receipt += padText(name, 14) + ' ';
            receipt += padText(qty.toString(), 3, 'center') + ' ';
            // Price and amount (bold)
            receipt += ESC_POS.BOLD_ON;
            receipt += padText(formatPrinterMoney(price), 10, 'right') + ' ';
            receipt += padText(formatPrinterMoney(total), 10, 'right');
            receipt += ESC_POS.BOLD_OFF;
            receipt += '\n';
            
            // Show discount if applicable
            if (discount > 0) {
                receipt += '  Discount: ';
                receipt += ESC_POS.BOLD_ON;
                receipt += '-' + formatPrinterMoney(discount);
                receipt += ESC_POS.BOLD_OFF;
                receipt += '\n';
            }
        });
        
        receipt += '\n';
        receipt += ESC_POS.LINE;
        receipt += '\n';
        
        // Totals section - right aligned with bold amounts
        receipt += ESC_POS.ALIGN_RIGHT;
        
        // Show subtotal and discount if there are discounts
        if (totalDiscount > 0) {
            receipt += 'Subtotal:   ';
            receipt += ESC_POS.BOLD_ON;
            receipt += formatPrinterMoney(grandTotal + totalDiscount);
            receipt += ESC_POS.BOLD_OFF;
            receipt += '\n';
            
            receipt += 'Discount:   ';
            receipt += ESC_POS.BOLD_ON;
            receipt += '-' + formatPrinterMoney(totalDiscount);
            receipt += ESC_POS.BOLD_OFF;
            receipt += '\n';
            receipt += '                    --------\n';
        }
        
        // Grand total - prominent with double height
        receipt += '\n';
        receipt += ESC_POS.BOLD_ON;
        receipt += ESC_POS.DOUBLE_HEIGHT_ON;
        receipt += 'GRAND TOTAL: ' + formatPrinterMoney(data.grand_total || grandTotal) + '\n';
        receipt += ESC_POS.NORMAL_SIZE;
        receipt += ESC_POS.BOLD_OFF;
        receipt += '\n';
        receipt += ESC_POS.LINE;
        receipt += '\n';
        
        // Payment method - clearly formatted
        receipt += ESC_POS.ALIGN_LEFT;
        receipt += ESC_POS.BOLD_ON;
        receipt += 'PAYMENT METHOD: ';
        receipt += ESC_POS.BOLD_OFF;
        
        // Format payment breakdown if available
        if (paymentBreakdown && typeof paymentBreakdown === 'object' && (paymentBreakdown.cash > 0 || paymentBreakdown.card > 0 || paymentBreakdown.transfer > 0)) {
            const parts = [];
            if (paymentBreakdown.cash > 0) {
                parts.push('Cash (' + formatPrinterMoney(paymentBreakdown.cash) + ')');
            }
            if (paymentBreakdown.card > 0) {
                parts.push('Card (' + formatPrinterMoney(paymentBreakdown.card) + ')');
            }
            if (paymentBreakdown.transfer > 0) {
                parts.push('Transfer (' + formatPrinterMoney(paymentBreakdown.transfer) + ')');
            }
            receipt += parts.join(' + ');
        } else if (data.payment_method) {
            receipt += formatPaymentMethod(data.payment_method);
        } else {
            receipt += 'N/A';
        }
        receipt += '\n';
        receipt += '\n';
        receipt += ESC_POS.LINE;
        receipt += '\n';
        
        // Footer - centered
        receipt += ESC_POS.ALIGN_CENTER;
        receipt += 'Thank you for your purchase!\n';
        receipt += 'Please come again\n';
        receipt += '\n';
        receipt += ESC_POS.LINE;
        
        // Feed and cut
        receipt += ESC_POS.FEED;
        receipt += ESC_POS.PARTIAL_CUT;
        
        return receipt;
    }
    
    /**
     * Check if Web Bluetooth API is available
     */
    function isBluetoothSupported() {
        return navigator.bluetooth !== undefined;
    }
    
    /**
     * Connect to Bluetooth printer and print receipt
     */
    async function printViaBluetooth(receiptData, isReprint = false) {
        if (!isBluetoothSupported()) {
            showNotification('Bluetooth not supported. Using browser print...', 'warning');
            fallbackToBrowserPrint(receiptData, isReprint);
            return;
        }
        
        try {
            showNotification('Scanning for Bluetooth printers...', 'info');
            
            // Request Bluetooth device with printer service
            // Using acceptAllDevices to support maximum printer compatibility
            const device = await navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: [
                    // Common Bluetooth printer service UUIDs used by various manufacturers:
                    // - 000018f0: Standard Serial Port Profile (SPP) - Most ESC/POS printers
                    // - 49535343: Microchip/Nordic UART Service - Many portable printers
                    // - e7810a71: Custom service used by some Chinese thermal printers
                    '000018f0-0000-1000-8000-00805f9b34fb',
                    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                    'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
                ]
            });
            
            showNotification('Connecting to ' + device.name + '...', 'info');
            
            const server = await device.gatt.connect();
            
            // Try to find a writable characteristic
            let characteristic = null;
            
            // Try each common printer service UUID until we find a writable characteristic
            const serviceUUIDs = [
                '000018f0-0000-1000-8000-00805f9b34fb',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
            ];
            
            for (const serviceUUID of serviceUUIDs) {
                try {
                    const service = await server.getPrimaryService(serviceUUID);
                    const characteristics = await service.getCharacteristics();
                    
                    for (const char of characteristics) {
                        if (char.properties.write || char.properties.writeWithoutResponse) {
                            characteristic = char;
                            break;
                        }
                    }
                    
                    if (characteristic) break;
                } catch (e) {
                    // Continue trying other services
                }
            }
            
            if (!characteristic) {
                throw new Error('No writable characteristic found on printer');
            }
            
            // Generate ESC/POS receipt
            const receiptText = generateEscPosReceipt(receiptData, isReprint);
            const encoder = new TextEncoder();
            const data = encoder.encode(receiptText);
            
            // BLE has a maximum transmission unit (MTU) limit, typically 20-23 bytes
            // for older devices. We use 20 bytes to ensure maximum compatibility.
            const chunkSize = 20;
            for (let i = 0; i < data.length; i += chunkSize) {
                const chunk = data.slice(i, i + chunkSize);
                if (characteristic.properties.writeWithoutResponse) {
                    await characteristic.writeValueWithoutResponse(chunk);
                } else {
                    await characteristic.writeValue(chunk);
                }
                // 50ms delay between chunks prevents buffer overflow on slower
                // thermal printers and ensures reliable data transmission
                await new Promise(resolve => setTimeout(resolve, 50));
            }
            
            showNotification('Receipt printed successfully!', 'success');
            
            // Disconnect
            device.gatt.disconnect();
            
        } catch (error) {
            console.error('Bluetooth print error:', error);
            
            if (error.name === 'NotFoundError') {
                showNotification('No Bluetooth printer selected. Using browser print...', 'warning');
            } else {
                showNotification('Bluetooth error: ' + error.message + '. Using browser print...', 'warning');
            }
            
            fallbackToBrowserPrint(receiptData, isReprint);
        }
    }
    
    /**
     * Fallback to browser print dialog
     */
    function fallbackToBrowserPrint(receiptData, isReprint = false) {
        const receiptText = generateTextReceipt(receiptData, isReprint);
        
        // Create a print window with the receipt
        const printWindow = window.open('', '_blank', 'width=400,height=600');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Receipt</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 12px;
                        line-height: 1.4;
                        padding: 10px;
                        width: 80mm;
                        max-width: 100%;
                    }
                    pre {
                        white-space: pre-wrap;
                        word-wrap: break-word;
                        font-family: inherit;
                        font-size: inherit;
                    }
                    @media print {
                        body {
                            width: 80mm;
                            padding: 0;
                            margin: 0;
                        }
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                    }
                </style>
            </head>
            <body>
                <pre>${receiptText}</pre>
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            window.close();
                        };
                    };
                </script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
    }
    
    /**
     * Download receipt as text file
     */
    function downloadReceiptFile(receiptData, isReprint = false) {
        const receiptText = generateTextReceipt(receiptData, isReprint);
        const filename = 'receipt_' + (receiptData.receipt_number || receiptData.order_id || 'order') + '.txt';
        
        const blob = new Blob([receiptText], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showNotification('Receipt downloaded: ' + filename, 'success');
    }
    
    /**
     * Print receipt (tries Bluetooth first, then browser)
     */
    window.printReceipt = function() {
        if (!currentReceiptData) {
            showNotification('No receipt data available', 'error');
            return;
        }
        
        printViaBluetooth(currentReceiptData, false);
    };
    
    /**
     * Download receipt
     */
    window.downloadReceipt = function() {
        if (!currentReceiptData) {
            showNotification('No receipt data available', 'error');
            return;
        }
        
        downloadReceiptFile(currentReceiptData, false);
    };
    
    /**
     * Store receipt data for printing (called after order confirmation)
     */
    window.setReceiptData = function(data) {
        currentReceiptData = data;
    };
    
    /**
     * Reprint receipt from order history
     */
    window.reprintReceipt = function(orderData) {
        if (!orderData) {
            showNotification('No order data available for reprint', 'error');
            return;
        }
        
        printViaBluetooth(orderData, true);
    };
    
    /**
     * Download receipt from order history
     */
    window.downloadHistoryReceipt = function(orderData) {
        if (!orderData) {
            showNotification('No order data available', 'error');
            return;
        }
        
        downloadReceiptFile(orderData, true);
    };
    
    // Store current order for history reprint
    let currentHistoryOrder = null;
    
    window.setHistoryOrderData = function(data) {
        currentHistoryOrder = data;
    };
    
    // Handle reprint button in view order modal
    $(document).on('click', '#reprintOrderBtn', function() {
        if (currentHistoryOrder) {
            printViaBluetooth(currentHistoryOrder, true);
        } else {
            showNotification('No order data available for reprint', 'error');
        }
    });
    
    // Handle download button in view order modal
    $(document).on('click', '#downloadHistoryReceiptBtn', function() {
        if (currentHistoryOrder) {
            downloadReceiptFile(currentHistoryOrder, true);
        } else {
            showNotification('No order data available', 'error');
        }
    });
    
    // Handle print button in order history table rows
    $(document).on('click', '.print-order-btn', function() {
        const orderId = $(this).data('order-id');
        
        $.ajax({
            url: capitito_ims_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'capitito_ims_get_order',
                nonce: capitito_ims_ajax.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    printViaBluetooth(response.data, true);
                } else {
                    showNotification('Failed to load order data', 'error');
                }
            },
            error: function() {
                showNotification('Error loading order data', 'error');
            }
        });
    });

})(jQuery);