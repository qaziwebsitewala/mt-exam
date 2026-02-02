(function($) {
    'use strict';

    const ExamList = {
        currentPage: 1,
        currentFilter: 'all',
        allExams: [],

        init: function() {
            this.loadExams();
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.em-filter-btn', this.handleFilter.bind(this));
            $(document).on('click', '.em-pagination-btn', this.handlePagination.bind(this));
        },

        loadExams: function() {
            const wrapper = $('.em-exam-list-wrapper');
            const perPage = wrapper.data('per-page') || 10;

            $.ajax({
                url: emExamList.ajaxurl,
                type: 'GET',
                data: {
                    action: 'em_get_exams',
                    nonce: emExamList.nonce,
                    page: this.currentPage,
                    per_page: perPage
                },
                success: (response) => {
                    if (response.success) {
                        this.allExams = response.data.exams;
                        this.renderExams(response.data);
                        $('.em-exam-list-loading').hide();
                        $('.em-exam-list-container').show();
                    } else {
                        this.showError();
                    }
                },
                error: () => {
                    this.showError();
                }
            });
        },

        renderExams: function(data) {
            const container = $('.em-exam-list');
            const filtered = this.currentFilter === 'all' 
                ? data.exams 
                : data.exams.filter(exam => exam.status === this.currentFilter);

            if (filtered.length === 0) {
                container.html('<p class="em-no-exams">No exams found.</p>');
                $('.em-exam-pagination').html('');
                return;
            }

            let html = '';
            filtered.forEach(exam => {
                const statusClass = `em-exam-status-${exam.status}`;
                html += `
                    <div class="em-exam-item ${statusClass}">
                        <h3 class="em-exam-title">
                            <a href="${exam.permalink}">${exam.title}</a>
                        </h3>
                        <div class="em-exam-meta">
                            <span class="em-exam-status-badge ${statusClass}">${exam.status}</span>
                            <span class="em-exam-datetime">
                                <strong>Start:</strong> ${exam.start_formatted}
                            </span>
                            <span class="em-exam-datetime">
                                <strong>End:</strong> ${exam.end_formatted}
                            </span>
                            <span class="em-exam-duration">
                                <strong>Duration:</strong> ${exam.duration}
                            </span>
                        </div>
                    </div>
                `;
            });

            container.html(html);
            this.renderPagination(data.pagination);
        },

        renderPagination: function(pagination) {
            if (pagination.total_pages <= 1) {
                $('.em-exam-pagination').html('');
                return;
            }

            let html = '<div class="em-pagination">';
            
            if (pagination.has_previous) {
                html += `<button class="em-pagination-btn" data-page="${pagination.page - 1}">Previous</button>`;
            }

            html += `<span class="em-pagination-info">Page ${pagination.page} of ${pagination.total_pages}</span>`;

            if (pagination.has_next) {
                html += `<button class="em-pagination-btn" data-page="${pagination.page + 1}">Next</button>`;
            }

            html += '</div>';
            $('.em-exam-pagination').html(html);
        },

        handleFilter: function(e) {
            const filter = $(e.target).data('filter');
            this.currentFilter = filter;
            this.currentPage = 1;

            $('.em-filter-btn').removeClass('active');
            $(e.target).addClass('active');

            this.loadExams();
        },

        handlePagination: function(e) {
            const page = $(e.target).data('page');
            this.currentPage = page;
            this.loadExams();
            $('html, body').animate({ scrollTop: $('.em-exam-list-wrapper').offset().top - 100 }, 500);
        },

        showError: function() {
            $('.em-exam-list-loading').hide();
            $('.em-exam-list-container').hide();
            $('.em-exam-list-error').show();
        }
    };

    $(document).ready(function() {
        if ($('.em-exam-list-wrapper').length) {
            ExamList.init();
        }
    });

})(jQuery);