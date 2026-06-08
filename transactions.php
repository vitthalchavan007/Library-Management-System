<?php
// api/transactions.php
function handleGetTransactions($db) {
    $limit = $_GET['limit'] ?? 50;
    $query = "SELECT * FROM transactions ORDER BY transaction_date DESC LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(true, 'Transactions retrieved', $transactions);
}

function handleBorrowBook($db, $data) {
    $studentId = $data['student_id'];
    $bookId = $data['book_id'];
    
    $db->beginTransaction();
    
    try {
        // Check if book is available
        $bookQuery = "SELECT title, available, quantity FROM books WHERE id = :id FOR UPDATE";
        $bookStmt = $db->prepare($bookQuery);
        $bookStmt->bindParam(':id', $bookId);
        $bookStmt->execute();
        $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$book || $book['available'] <= 0) {
            throw new Exception('Book not available');
        }
        
        // Check if student already borrowed this book
        $checkQuery = "SELECT id FROM borrowed_books 
                       WHERE student_id = :student_id AND book_id = :book_id AND status = 'active'";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':student_id', $studentId);
        $checkStmt->bindParam(':book_id', $bookId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception('Student already borrowed this book');
        }
        
        // Update book availability
        $updateQuery = "UPDATE books SET available = available - 1 WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':id', $bookId);
        $updateStmt->execute();
        
        // Add to borrowed_books
        $borrowDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+14 days'));
        
        $borrowQuery = "INSERT INTO borrowed_books (student_id, book_id, book_title, borrow_date, due_date, status) 
                        VALUES (:student_id, :book_id, :book_title, :borrow_date, :due_date, 'active')";
        $borrowStmt = $db->prepare($borrowQuery);
        $borrowStmt->bindParam(':student_id', $studentId);
        $borrowStmt->bindParam(':book_id', $bookId);
        $borrowStmt->bindParam(':book_title', $book['title']);
        $borrowStmt->bindParam(':borrow_date', $borrowDate);
        $borrowStmt->bindParam(':due_date', $dueDate);
        $borrowStmt->execute();
        
        // Get student name for transaction
        $studentQuery = "SELECT name FROM students WHERE id = :id";
        $studentStmt = $db->prepare($studentQuery);
        $studentStmt->bindParam(':id', $studentId);
        $studentStmt->execute();
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        
        // Add transaction record
        $txQuery = "INSERT INTO transactions (student_id, student_name, book_id, book_title, type, transaction_date) 
                    VALUES (:student_id, :student_name, :book_id, :book_title, 'borrow', :date)";
        $txStmt = $db->prepare($txQuery);
        $txStmt->bindParam(':student_id', $studentId);
        $txStmt->bindParam(':student_name', $student['name']);
        $txStmt->bindParam(':book_id', $bookId);
        $txStmt->bindParam(':book_title', $book['title']);
        $txStmt->bindParam(':date', $borrowDate);
        $txStmt->execute();
        
        $db->commit();
        sendResponse(true, 'Book borrowed successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, $e->getMessage(), null, 400);
    }
}

function handleReturnBook($db, $data) {
    $studentId = $data['student_id'];
    $bookId = $data['book_id'];
    
    $db->beginTransaction();
    
    try {
        // Check if book is borrowed by this student
        $checkQuery = "SELECT id FROM borrowed_books 
                       WHERE student_id = :student_id AND book_id = :book_id AND status = 'active'";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':student_id', $studentId);
        $checkStmt->bindParam(':book_id', $bookId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Book not borrowed by this student');
        }
        
        // Update borrowed_books status
        $updateQuery = "UPDATE borrowed_books SET status = 'returned' 
                        WHERE student_id = :student_id AND book_id = :book_id AND status = 'active'";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':student_id', $studentId);
        $updateStmt->bindParam(':book_id', $bookId);
        $updateStmt->execute();
        
        // Update book availability
        $bookUpdate = "UPDATE books SET available = available + 1 WHERE id = :id";
        $bookStmt = $db->prepare($bookUpdate);
        $bookStmt->bindParam(':id', $bookId);
        $bookStmt->execute();
        
        // Get book title and student name
        $bookQuery = "SELECT title FROM books WHERE id = :id";
        $bookStmt = $db->prepare($bookQuery);
        $bookStmt->bindParam(':id', $bookId);
        $bookStmt->execute();
        $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
        
        $studentQuery = "SELECT name FROM students WHERE id = :id";
        $studentStmt = $db->prepare($studentQuery);
        $studentStmt->bindParam(':id', $studentId);
        $studentStmt->execute();
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        
        // Add transaction record
        $returnDate = date('Y-m-d');
        $txQuery = "INSERT INTO transactions (student_id, student_name, book_id, book_title, type, transaction_date) 
                    VALUES (:student_id, :student_name, :book_id, :book_title, 'return', :date)";
        $txStmt = $db->prepare($txQuery);
        $txStmt->bindParam(':student_id', $studentId);
        $txStmt->bindParam(':student_name', $student['name']);
        $txStmt->bindParam(':book_id', $bookId);
        $txStmt->bindParam(':book_title', $book['title']);
        $txStmt->bindParam(':date', $returnDate);
        $txStmt->execute();
        
        $db->commit();
        sendResponse(true, 'Book returned successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, $e->getMessage(), null, 400);
    }
}
?>