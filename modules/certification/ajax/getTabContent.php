<?php
/**
 * Certification module....
 *
 * PHP Version 5
 *
 * @category Behavioural
 * @package  Loris
 * @author   Tara Campbell <tara.campbell@mail.mcgill.ca>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://github.com/aces/Loris
 */
set_include_path(get_include_path().":../project/libraries:../php/libraries:");
ini_set('default_charset', 'utf-8');

require_once "Database.class.inc";
require_once 'NDB_Config.class.inc';
require_once 'NDB_Client.class.inc';
$config =& NDB_Config::singleton();
$client = new NDB_Client();
$client->makeCommandLine();
$client->initialize();

$DB = Database::singleton();

// Get the ID for the instrument that was selected
$instrumentID = $_REQUEST['instrument'];
$tabID = $_REQUEST['tabNumber'];

// Check the tabs and their titles
$tabInformation = $DB->pselect(
    "SELECT Title, Content, TrainingType FROM certification_training WHERE TestID=:TID AND OrderNumber=:TNO",
    array('TID' => $instrumentID, 'TNO' => $tabID)
);

$tabContent = $tabInformation[0]['Content'];

if ($tabInformation[0]['TrainingType'] == 'text') {
    $tabHTML = createTabHTML(0, 'Please read the following:', $tabInformation[0]['Title'], $tabContent, 'Agree', 'I have completed reading this section of the training module.'); 
}
else if ($tabInformation[0]['TrainingType'] == 'video') {
    $tabHTML = createTabHTML(0, 'Please watch the following:', $tabInformation[0]['Title'], $tabContent, 'Agree', 'I have completed watching this section of the training module.'); 
}
else if ($tabInformation[0]['TrainingType'] == 'quiz') {
    $tabHTML = createTabHTML(1, 'Please complete the quiz below in order to receive certification:', $tabInformation[0]['Title'], createQuiz($instrumentID), 'Submit', 'Submit your answers to the quiz. If any answers are incorrect, you will be prompted to repeat the certification training.'); 
    $tabContent = '<div class="panel panel-default training-instructions"><div class="panel-body">Please complete the quiz below in order to receive certification:</div></div>' . '<div class="training-content"><h3>' . $tabInformation[0]['Title'] . '</h3><form id="quiz">' . $quizHTML . '</form></div>' . '<div class="well well-sm training-complete"><button type="submit" form="quiz" id="quizSubmit" class="btn btn-default btn-submit btn-success" type="button">Submit</button> Submit your answers to the quiz. If any answers are incorrect, you will be prompted to repeat the certification training.</div>';
}

print $tabHTML;

exit();

function createQuizRadio($questionNumber, $answerNumber, $answer) {
    return '<div class="radio"><label><input type="radio" name="' 
           . 'q' 
           . $questionNumber 
           . '" id="q' 
           . $questionNumber 
           . '-' 
           . $answerNumber 
           . '" value="' 
           . $answerNumber 
           . '">' 
           . $answer
           . '</label></div>';
}

function createQuiz($instrumentID) {
    $DB =& Database::singleton();
    // Get the questions
    $questions = $DB->pselect(
        "SELECT Question, OrderNumber, ID 
         FROM certification_training_quiz_questions 
         WHERE TestID=:TID 
         ORDER BY OrderNumber",
        array('TID' => $instrumentID)
    );

    // Add the array of answers to each question
    foreach ($questions as $i => $question) {
        $questions[$i]['answers'] = $DB->pselect(
            "SELECT a.Answer, a.OrderNumber 
             FROM certification_training_quiz_answers a 
             LEFT JOIN certification_training_quiz_questions q ON (a.QuestionID=q.ID) 
             WHERE q.TestID=:TID AND a.QuestionID=:QID 
             ORDER BY OrderNumber",
            array('TID' => $instrumentID, 'QID' => $question['ID'])
        );
    }
    
    // Create the quiz html
    $quizHTML = '';
    foreach ($questions as $question) {
        $quizHTML .= '<p><b>' . $question['OrderNumber'] . '. ' . $question['Question'] . '</b></p>';
        foreach ($question['answers'] as $answer) {
            $quizHTML .= createQuizRadio($question['OrderNumber'], $answer['OrderNumber'], $answer['Answer']);
        }
    }
    return $quizHTML;
}

function createTabHTML($quiz, $instructions, $title, $tabContent, $button, $message) {
    $instructionPanel = '<div class="panel panel-default training-instructions"><div class="panel-body">'
                         . $instructions
                         . '</div></div>';

    $mainContent = '<div class="training-content"><h3>' 
                    . $title
                    . '</h3>'
                    . ($quiz == 1 ? '<form id="quiz">' : '')
                    . $tabContent
                    . ($quiz == 1 ? '</form>' : '')
                    . '</div>';
    
    $buttonPanel = '<div class="well well-sm training-complete"><button '
                    . ($quiz == 1 ? 'type="submit" form="quiz" id="quizSubmit" ' : '')
                    . 'class="btn btn-default btn-agree btn-success" type="button">'
                    . $button
                    . '</button> '
                    . $message
                    . '</div>';
    
    $html = $instructionPanel . $mainContent . $buttonPanel; 

    return $html;
}
?>