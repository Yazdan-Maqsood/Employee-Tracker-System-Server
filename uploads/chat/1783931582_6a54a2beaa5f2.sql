-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2026 at 02:58 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `academic_bridge`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `adminName` varchar(100) NOT NULL,
  `adminEmail` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Super Admin','Admin','Manager') DEFAULT 'Admin',
  `profilePicture` varchar(500) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `lastLogin` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `adminName`, `adminEmail`, `password`, `role`, `profilePicture`, `phone`, `status`, `lastLogin`, `createdAt`, `updatedAt`, `user_id`) VALUES
(2, 'Yazdan Maqsood', 'yazdanmaqsood2@gmail.com', '$2b$10$ImJ1RAM3Alt7dzxCoACXneviot6VZ047R5RWSzIzDqWTp8fhoiVZa', 'Admin', 'uploads/admins_Images/admin-1777977232773-200348454.JPG', '03156464706', 'Active', NULL, '2026-04-24 09:53:53', '2026-05-05 10:33:52', 25);

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `courseId` int(11) DEFAULT NULL COMMENT 'NULL means all students',
  `announcementType` enum('General','Exam','Event','Holiday') DEFAULT 'General',
  `priority` enum('High','Medium','Normal') DEFAULT 'Normal',
  `validFrom` date DEFAULT NULL,
  `validTo` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `attachments` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `courseId`, `announcementType`, `priority`, `validFrom`, `validTo`, `status`, `createdAt`, `updatedAt`, `attachments`) VALUES
(14, 'Computers are ready for mobile app developers', 'We have ready the computers for mobile app developers for practicing and learning', 9, 'General', 'Medium', '2026-05-01', '2026-05-02', 'Active', '2026-05-01 05:59:39', '2026-05-01 05:59:39', 'uploads\\announcement_attachments\\1777615179337-saudi niqab size chart.png'),
(15, 'Web Developers must bring their laptops', 'Web Developers must bring their laptops, Because the computer lab is booked for mobile app developers for some days, Thank you so much, we hope that you will cooperate with us ', 8, 'General', 'High', '2026-05-04', '2026-05-11', 'Active', '2026-05-01 06:07:33', '2026-05-01 06:07:33', 'uploads\\announcement_attachments\\1777615653673-2.png');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `announcementId` int(11) NOT NULL,
  `studentId` int(11) NOT NULL,
  `isRead` tinyint(1) DEFAULT 0,
  `readAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`id`, `announcementId`, `studentId`, `isRead`, `readAt`) VALUES
(8, 15, 24, 1, '2026-05-07 11:25:29'),
(9, 14, 24, 1, '2026-05-07 12:15:38');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `courseId` int(11) NOT NULL,
  `dueDate` date NOT NULL,
  `totalMarks` int(11) NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `title`, `description`, `courseId`, `dueDate`, `totalMarks`, `instructions`, `status`, `created_at`, `updated_at`) VALUES
(7, 'Key Points of Motivation ', 'Assignment of topic Key Points motivation, How they work in motivation ?', 10, '2026-05-04', 10, 'You should have to complete this assignment till 4th May 2026, Thank you so much, Best of Luck ', 'Active', '2026-05-01 05:14:31', '2026-05-01 05:14:31'),
(8, 'DOM Manipulation', 'JavaScript DOM manipulation and interaction with HTML and CSS to enhance the website design ', 8, '2026-05-11', 10, 'You must have to complete this assignment till 5th May 2026, Best of Luck , Happy Coding ❤', 'Active', '2026-05-01 05:17:11', '2026-05-07 11:16:32');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `id` int(11) NOT NULL,
  `assignmentId` int(11) NOT NULL,
  `studentId` int(11) NOT NULL,
  `submissionText` text DEFAULT NULL,
  `submissionFile` varchar(500) DEFAULT NULL,
  `submittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `obtainedMarks` decimal(10,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `gradedAt` timestamp NULL DEFAULT NULL,
  `status` enum('Submitted','Graded','Passed','Failed') DEFAULT 'Submitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment_submissions`
--

INSERT INTO `assignment_submissions` (`id`, `assignmentId`, `studentId`, `submissionText`, `submissionFile`, `submittedAt`, `obtainedMarks`, `feedback`, `gradedAt`, `status`) VALUES
(19, 8, 24, 'completed', 'uploads\\student_assignment_submission\\submission-1778155710992-145931747.pdf', '2026-05-07 12:08:32', 7.00, 'good', '2026-05-07 12:15:03', 'Passed');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `courseCode` varchar(10) NOT NULL,
  `courseName` varchar(50) NOT NULL,
  `credits` varchar(5) NOT NULL,
  `instructor` varchar(50) NOT NULL,
  `department` varchar(50) NOT NULL,
  `status` varchar(10) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `courseFile` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `courseCode`, `courseName`, `credits`, `instructor`, `department`, `status`, `description`, `courseFile`) VALUES
(6, 'CS-202', 'Database Systems', '4', 'SIr. Adeel ', 'Computer Science', 'Active', 'Database Management System with relational and irrelational databases', 'uploads\\courses\\course-1776980762456-982173471.pdf'),
(8, 'CS-234', 'JavaScript', '3', 'Sir . Ahmed', 'Software Engineering', 'Active', 'JavaScript is a programming course that teaches the fundamentals of web scripting, including variables, functions, DOM manipulation, events, ES6 features, asynchronous programming, and building interactive, dynamic applications for modern web development.', 'uploads\\courses\\course-1777093083697-343447146.pdf'),
(9, 'CS-104', 'Mobile App Development', '3', 'Sir. Jameel', 'Software Engineering', 'Active', 'Data Structures is a core computer science course focused on organizing, storing, and managing data efficiently. It covers arrays, linked lists, stacks, queues, trees, graphs, hashing, and algorithms for searching, sorting, and optimization.', 'uploads\\courses\\course-1777093282745-135891157.pdf'),
(10, 'CS-600', 'Phsycology', '3', 'Dr. Ayesha', 'Medical', 'Active', 'dhsuahduasduiashdiusadsd', 'uploads\\courses\\course-1777526114529-907528226.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `courseId` int(11) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `totalMarks` int(11) NOT NULL,
  `passingMarks` int(11) NOT NULL,
  `startDate` datetime NOT NULL,
  `endDate` datetime NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `title`, `description`, `courseId`, `duration`, `totalMarks`, `passingMarks`, `startDate`, `endDate`, `instructions`, `status`, `created_at`, `updated_at`) VALUES
(3, 'Flutter Widgets', 'Assess your understanding of Flutter widgets, layouts, state management basics, and UI building concepts through practical multiple-choice questions.', 9, 60, 20, 10, '2026-05-07 16:00:00', '2026-05-07 17:00:00', 'Answer all questions about Flutter widgets carefully. Choose the most accurate option for each. No negative marking. Complete and submit before the timer expires.', 'Active', '2026-04-29 05:26:28', '2026-05-07 11:23:18'),
(5, 'Motivation', 'This quiz evaluates students’ understanding of the psychological concept of motivation, including intrinsic and extrinsic motivation, theories of motivation, Maslow’s hierarchy of needs, drive reduction theory, and achievement motivation.', 10, 30, 20, 10, '2026-05-02 15:25:00', '2026-05-02 16:25:00', 'Read each question carefully before answering.\nSelect the one best answer for each multiple-choice question.\nEach question carries equal marks.\nNo negative marking unless specified by instructor.\nSubmit the quiz before the time limit expires.\nOnce submitted, answers cannot be changed.', 'Active', '2026-05-01 04:40:35', '2026-05-02 10:25:55');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quizId` int(11) NOT NULL,
  `questionText` text NOT NULL,
  `optionA` varchar(500) NOT NULL,
  `optionB` varchar(500) NOT NULL,
  `optionC` varchar(500) NOT NULL,
  `optionD` varchar(500) NOT NULL,
  `correctAnswer` enum('A','B','C','D') NOT NULL,
  `marks` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quizId`, `questionText`, `optionA`, `optionB`, `optionC`, `optionD`, `correctAnswer`, `marks`, `created_at`) VALUES
(18, 3, 'Which widget is used to create a scrollable list in Flutter?', 'Container', 'ListView', 'Column', 'Stack', 'D', 5, '2026-04-29 07:05:54'),
(19, 3, 'Which widget is used to arrange children vertically in Flutter?', 'Row', 'Column', 'Stack', 'Wrap', 'B', 5, '2026-04-29 07:06:29'),
(20, 3, 'Which widget is used to add padding around another widget?', 'Margin', 'Spacer', 'Padding', 'Align', 'C', 5, '2026-04-29 07:07:11'),
(21, 3, 'Which widget is used for clickable buttons in Flutter?', 'GestureWidget ', 'ElevatedButton', 'ButtonWidget ', 'TapButton', 'A', 5, '2026-04-29 07:07:55'),
(26, 5, 'What is motivation in psychology?', 'A type of memory', 'need or desire that energizes behavior and directs it toward a goal ✅', 'A learning disability', 'A form of intelligence', 'A', 5, '2026-05-01 04:41:24'),
(27, 5, 'Which of the following is an example of intrinsic motivation?', 'Studying to get money', 'Exercising because you enjoy it', 'Working for a salary', 'Reading to avoid punishment', 'C', 5, '2026-05-01 04:42:14'),
(28, 5, 'According to Maslow, which need must be met first?', 'Esteem needs', 'Safety needs', 'Physiological needs', 'Self-actualization', 'D', 5, '2026-05-01 04:42:57'),
(29, 5, 'What does self-actualization mean in Maslow’s theory?', 'Basic survival', 'Achieving one’s full potential', 'Feeling safe', 'Being socially accepted', 'A', 5, '2026-05-01 04:44:12');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_results`
--

CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL,
  `studentId` int(11) NOT NULL,
  `quizId` int(11) NOT NULL,
  `obtainedMarks` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Passed','Failed') DEFAULT 'Pending',
  `submittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `gradedAt` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `course_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_results`
--

INSERT INTO `quiz_results` (`id`, `studentId`, `quizId`, `obtainedMarks`, `status`, `submittedAt`, `gradedAt`, `created_at`, `updated_at`, `course_id`) VALUES
(23, 24, 3, 10.00, 'Passed', '2026-05-07 11:24:21', '2026-05-07 11:24:21', '2026-05-07 11:24:21', '2026-05-07 11:24:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `studentName` varchar(50) NOT NULL,
  `studentEmail` varchar(100) NOT NULL,
  `enroll_courses` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL,
  `rollNumber` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `profilePicture` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `studentName`, `studentEmail`, `enroll_courses`, `status`, `rollNumber`, `department`, `profilePicture`, `user_id`, `password`, `role`, `phone`, `createdAt`) VALUES
(24, 'Jack', 'jack@gmail.com', 2, 'active', 'BSCS E1-22-29', 'Computer Science', 'uploads\\students_Images\\student-1778155542996-406760441.jpg', 34, '$2b$10$aJHNxSekSJtvirmzQ7ucku.jDwdZH/RtoOnk1rQoKHrfx2bayei62', 'student', '03156464777', '2026-05-07 06:22:45');

-- --------------------------------------------------------

--
-- Table structure for table `student_courses`
--

CREATE TABLE `student_courses` (
  `id` int(11) NOT NULL,
  `studentId` int(11) NOT NULL,
  `courseId` int(11) NOT NULL,
  `enrollDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Completed','Dropped') DEFAULT 'Active',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_courses`
--

INSERT INTO `student_courses` (`id`, `studentId`, `courseId`, `enrollDate`, `status`, `createdAt`, `updatedAt`) VALUES
(27, 24, 8, '2026-05-07 11:15:36', 'Active', '2026-05-07 11:15:36', '2026-05-07 11:15:36'),
(28, 24, 9, '2026-05-07 11:23:45', 'Active', '2026-05-07 11:23:45', '2026-05-07 11:23:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullName` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `role` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullName`, `email`, `password`, `phone`, `role`) VALUES
(25, 'Yazdan Maqsood', 'yazdanmaqsood2@gmail.com', '$2b$10$ImJ1RAM3Alt7dzxCoACXneviot6VZ047R5RWSzIzDqWTp8fhoiVZa', '03156464706', 'admin'),
(34, 'Jack', 'jack@gmail.com', '$2b$10$aJHNxSekSJtvirmzQ7ucku.jDwdZH/RtoOnk1rQoKHrfx2bayei62', '03156464777', 'student');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `adminEmail` (`adminEmail`),
  ADD KEY `idx_email` (`adminEmail`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `fk_admin_user` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course` (`courseId`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`announcementId`,`studentId`),
  ADD KEY `studentId` (`studentId`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `courseId` (`courseId`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`assignmentId`,`studentId`),
  ADD KEY `studentId` (`studentId`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `courseId` (`courseId`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quizId` (`quizId`);

--
-- Indexes for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`studentId`),
  ADD KEY `idx_quiz` (`quizId`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_students_user` (`user_id`);

--
-- Indexes for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`studentId`,`courseId`),
  ADD KEY `courseId` (`courseId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `student_courses`
--
ALTER TABLE `student_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`announcementId`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`studentId`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD CONSTRAINT `assignment_submissions_ibfk_1` FOREIGN KEY (`assignmentId`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_submissions_ibfk_2` FOREIGN KEY (`studentId`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`courseId`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quizId`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD CONSTRAINT `quiz_results_ibfk_1` FOREIGN KEY (`studentId`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_results_ibfk_2` FOREIGN KEY (`quizId`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`studentId`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_courses_ibfk_2` FOREIGN KEY (`courseId`) REFERENCES `courses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
