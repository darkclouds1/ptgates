jQuery(document).ready(function ($) {
    // Open Modal
    $(document).on('click', '.ptg-btn-flashcard', function (e) {
        e.preventDefault();

        // Extract Data from Quiz UI
        var questionText = $('.ptg-question-text').text().trim(); // Adjust selector based on actual quiz rendering
        var explanationText = $('.ptg-quiz-explanation').html(); // Get HTML for explanation
        var answerText = $('.ptg-quiz-answer-text').text(); // If available separately

        // If selectors fail, try to get from QuizState if accessible globally
        if (typeof QuizState !== 'undefined' && QuizState.currentQuestionData) {
            var q = QuizState.currentQuestionData;
            questionText = q.content;
            explanationText = "<strong>정답: " + q.answer + "</strong><br><br>" + q.explanation;
            $('#ptg-card-source-id').val(q.id);
            $('#ptg-card-subject').val(q.category ? q.category.subject : '');
        } else {
            // Fallback: Try to grab visible text
            questionText = $('#ptg-quiz-card').text().trim().substring(0, 200) + "...";
            explanationText = $('#ptg-quiz-explanation').html();
        }

        $('#ptg-card-front').val(questionText);
        $('#ptg-card-back').val(explanationText ? explanationText.replace(/<br\s*\/?>/gi, "\n").replace(/<\/?[^>]+(>|$)/g, "") : ''); // Strip HTML for textarea

        $('#ptg-flashcard-modal').fadeIn(200);
    });

    // Close Modal
    $('.ptg-flashcard-modal-close, .ptg-flashcard-modal-overlay').on('click', function () {
        $('#ptg-flashcard-modal').fadeOut(200);
    });

    // Submit Form
    $('#ptg-flashcard-form').on('submit', function (e) {
        e.preventDefault();

        var data = {
            front: $('#ptg-card-front').val(),
            back: $('#ptg-card-back').val(),
            source_id: $('#ptg-card-source-id').val(),
            subject: $('#ptg-card-subject').val()
        };

        $.ajax({
            url: ptgFlashcards.root + 'ptg-flashcards/v1/cards',
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ptgFlashcards.nonce);
            },
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function (response) {
                alert('암기카드가 생성되었습니다!');
                $('#ptg-flashcard-modal').fadeOut(200);
                $('#ptg-flashcard-form')[0].reset();
            },
            error: function (err) {
                alert('카드 생성 실패: ' + (err.responseJSON ? err.responseJSON.message : err.statusText));
            }
        });
    });
});
