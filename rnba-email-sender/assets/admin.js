(function($) {
    'use strict';

    var RNBAEmailSender = {
        customers: [],
        activeMode: 'products', // 'products' or 'emails'

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Tab switching
            $('.rnba-tab').on('click', this.switchTab.bind(this));

            // Actions
            $('#get_customers').on('click', this.getCustomers.bind(this));
            $('#send_test').on('click', this.sendTestEmail.bind(this));
            $('#send_emails').on('click', this.sendEmails.bind(this));
        },

        switchTab: function(e) {
            var $tab = $(e.currentTarget);
            var tabId = $tab.data('tab');

            // Update tabs
            $('.rnba-tab').removeClass('active');
            $tab.addClass('active');

            // Update content
            $('.rnba-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');

            // Update mode
            this.activeMode = tabId;

            // Reset customers
            this.customers = [];
            $('#customers_count .count').text('0');
            $('#customers_list').html('<p class="no-results">Спочатку знайдіть отримувачів</p>');
            $('#send_emails').prop('disabled', true);
        },

        getCustomers: function() {
            if (this.activeMode === 'products') {
                this.getCustomersByProducts();
            } else {
                this.getCustomersByEmailList();
            }
        },

        getCustomersByProducts: function() {
            var productIds = $('#product_ids').val().trim();

            if (!productIds) {
                this.showAlert('Введіть ID товарів');
                return;
            }

            var $button = $('#get_customers');
            var $list = $('#customers_list');
            var $count = $('#customers_count .count');

            $button.prop('disabled', true).text('Пошук...');

            $.ajax({
                url: rnbaEmailSender.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rnba_get_customers',
                    nonce: rnbaEmailSender.nonce,
                    product_ids: productIds
                },
                success: function(response) {
                    if (response.success) {
                        RNBAEmailSender.customers = response.data.customers;
                        $count.text(response.data.count);

                        if (response.data.count > 0) {
                            RNBAEmailSender.renderCustomersList(response.data.customers);
                            $('#send_emails').prop('disabled', false);
                        } else {
                            $list.html('<p class="no-results">Клієнтів не знайдено для вказаних товарів</p>');
                            $('#send_emails').prop('disabled', true);
                        }

                        RNBAEmailSender.addLog('Знайдено ' + response.data.count + ' клієнтів', 'success');
                    } else {
                        RNBAEmailSender.showAlert(response.data);
                        RNBAEmailSender.addLog(response.data, 'error');
                    }
                },
                error: function() {
                    RNBAEmailSender.showAlert('Помилка з\'єднання з сервером');
                    RNBAEmailSender.addLog('Помилка з\'єднання', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Знайти клієнтів');
                }
            });
        },

        getCustomersByEmailList: function() {
            var emailList = $('#email_list').val().trim();

            if (!emailList) {
                this.showAlert('Введіть список email адрес');
                return;
            }

            var $button = $('#get_customers');
            var $list = $('#customers_list');
            var $count = $('#customers_count .count');

            $button.prop('disabled', true).text('Обробка...');

            $.ajax({
                url: rnbaEmailSender.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rnba_parse_email_list',
                    nonce: rnbaEmailSender.nonce,
                    email_list: emailList
                },
                success: function(response) {
                    if (response.success) {
                        RNBAEmailSender.customers = response.data.customers;
                        $count.text(response.data.count);

                        if (response.data.count > 0) {
                            RNBAEmailSender.renderCustomersList(response.data.customers);
                            $('#send_emails').prop('disabled', false);
                        } else {
                            $list.html('<p class="no-results">Не знайдено коректних email адрес</p>');
                            $('#send_emails').prop('disabled', true);
                        }

                        RNBAEmailSender.addLog('Знайдено ' + response.data.count + ' email адрес', 'success');
                    } else {
                        RNBAEmailSender.showAlert(response.data);
                        RNBAEmailSender.addLog(response.data, 'error');
                    }
                },
                error: function() {
                    RNBAEmailSender.showAlert('Помилка з\'єднання з сервером');
                    RNBAEmailSender.addLog('Помилка з\'єднання', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Знайти клієнтів');
                }
            });
        },

        renderCustomersList: function(customers) {
            var html = '<table>';
            html += '<thead><tr><th>Email</th><th>Ім\'я</th></tr></thead>';
            html += '<tbody>';

            customers.forEach(function(customer) {
                var name = (customer.first_name + ' ' + customer.last_name).trim() || '—';
                html += '<tr>';
                html += '<td>' + this.escapeHtml(customer.email) + '</td>';
                html += '<td>' + this.escapeHtml(name) + '</td>';
                html += '</tr>';
            }, this);

            html += '</tbody></table>';

            $('#customers_list').html(html);
        },

        sendTestEmail: function() {
            var testEmail = $('#test_email').val().trim();
            var template = $('#email_template').val();
            var subject = $('#email_subject').val().trim();

            if (!testEmail) {
                this.showAlert('Введіть email для тесту');
                return;
            }

            if (!this.isValidEmail(testEmail)) {
                this.showAlert('Введіть коректний email');
                return;
            }

            if (!template) {
                this.showAlert('Виберіть шаблон листа');
                return;
            }

            if (!subject) {
                this.showAlert('Введіть тему листа');
                return;
            }

            var $button = $('#send_test');
            $button.prop('disabled', true).text('Відправка...');

            $.ajax({
                url: rnbaEmailSender.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rnba_send_test_email',
                    nonce: rnbaEmailSender.nonce,
                    test_email: testEmail,
                    template: template,
                    subject: subject
                },
                success: function(response) {
                    if (response.success) {
                        RNBAEmailSender.showAlert(response.data, 'success');
                        RNBAEmailSender.addLog('Тест: ' + testEmail + ' - надіслано', 'success');
                    } else {
                        RNBAEmailSender.showAlert(response.data);
                        RNBAEmailSender.addLog('Тест: ' + testEmail + ' - помилка', 'error');
                    }
                },
                error: function() {
                    RNBAEmailSender.showAlert('Помилка з\'єднання з сервером');
                    RNBAEmailSender.addLog('Помилка з\'єднання', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Надіслати тест');
                }
            });
        },

        sendEmails: function() {
            var template = $('#email_template').val();
            var subject = $('#email_subject').val().trim();

            if (!template) {
                this.showAlert('Виберіть шаблон листа');
                return;
            }

            if (!subject) {
                this.showAlert('Введіть тему листа');
                return;
            }

            if (this.customers.length === 0) {
                this.showAlert('Спочатку знайдіть отримувачів');
                return;
            }

            var confirmMsg = 'Ви впевнені, що хочете надіслати листи ' + this.customers.length + ' отримувачам?';
            if (!confirm(confirmMsg)) {
                return;
            }

            var $button = $('#send_emails');
            $button.prop('disabled', true).text('Відправка...');

            this.addLog('Початок розсилки на ' + this.customers.length + ' адрес...', 'info');

            var ajaxData = {
                nonce: rnbaEmailSender.nonce,
                template: template,
                subject: subject
            };

            if (this.activeMode === 'products') {
                ajaxData.action = 'rnba_send_emails';
                ajaxData.product_ids = $('#product_ids').val().trim();
            } else {
                ajaxData.action = 'rnba_send_to_email_list';
                ajaxData.email_list = $('#email_list').val().trim();
            }

            $.ajax({
                url: rnbaEmailSender.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var msg = 'Розсилка завершена: ' + data.sent + '/' + data.total + ' надіслано';

                        if (data.failed > 0) {
                            msg += ', ' + data.failed + ' помилок';
                        }

                        RNBAEmailSender.showAlert(msg, data.failed === 0 ? 'success' : 'warning');

                        // Add individual logs
                        data.log.forEach(function(entry) {
                            RNBAEmailSender.addLog(
                                entry.email + ' - ' + entry.message,
                                entry.status
                            );
                        });

                        RNBAEmailSender.addLog(msg, data.failed === 0 ? 'success' : 'error');
                    } else {
                        RNBAEmailSender.showAlert(response.data);
                        RNBAEmailSender.addLog(response.data, 'error');
                    }
                },
                error: function() {
                    RNBAEmailSender.showAlert('Помилка з\'єднання з сервером');
                    RNBAEmailSender.addLog('Помилка з\'єднання', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Надіслати листи');
                }
            });
        },

        addLog: function(message, type) {
            var $log = $('#send_log');
            var $empty = $log.find('.log-empty');

            if ($empty.length) {
                $empty.remove();
            }

            var time = new Date().toLocaleTimeString('uk-UA');
            var typeClass = type || 'info';

            var html = '<div class="log-entry ' + typeClass + '">';
            html += '<span class="time">[' + time + ']</span>';
            html += this.escapeHtml(message);
            html += '</div>';

            $log.append(html);
            $log.scrollTop($log[0].scrollHeight);
        },

        showAlert: function(message, type) {
            // Simple alert for now, can be replaced with a better notification system
            alert(message);
        },

        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        RNBAEmailSender.init();
    });

})(jQuery);
