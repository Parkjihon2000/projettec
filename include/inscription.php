<?php
// Start session
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ensa_project_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$first_name = $last_name = $email = $password = $confirm_password = "";
$student_id = $department = $year_of_study = "";
$role_id = "";
$errors = [];
$success_message = "";

// Get all roles for the dropdown
$roles_query = "SELECT role_id, role_name FROM roles";
$roles_result = $conn->query($roles_query);
$roles = [];
if ($roles_result->num_rows > 0) {
    while($role = $roles_result->fetch_assoc()) {
        $roles[] = $role;
    }
}

// Get all departments for the dropdown
$departments_query = "SELECT name FROM departments";
$departments_result = $conn->query($departments_query);
$departments = [];
if ($departments_result->num_rows > 0) {
    while($dept = $departments_result->fetch_assoc()) {
        $departments[] = $dept['name'];
    }
} else {
    // If no departments in database, add some defaults
    $departments = ["Computer Science", "Electrical Engineering", "Civil Engineering", "Mechanical Engineering"];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate first name
    if (empty($_POST["first_name"])) {
        $errors[] = "First name is required";
    } else {
        $first_name = test_input($_POST["first_name"]);
    }
    
    // Validate last name
    if (empty($_POST["last_name"])) {
        $errors[] = "Last name is required";
    } else {
        $last_name = test_input($_POST["last_name"]);
    }
    
    // Validate email
    if (empty($_POST["email"])) {
        $errors[] = "Email is required";
    } else {
        $email = test_input($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email already exists
        $email_check_query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($email_check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $stmt->close();
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $errors[] = "Password is required";
    } else {
        $password = test_input($_POST["password"]);
        // Password should be at least 8 characters
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
    }
    
    // Validate confirm password
    if (empty($_POST["confirm_password"])) {
        $errors[] = "Please confirm your password";
    } else {
        $confirm_password = test_input($_POST["confirm_password"]);
        if ($password != $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // Validate role
    if (empty($_POST["role_id"])) {
        $errors[] = "Role is required";
    } else {
        $role_id = test_input($_POST["role_id"]);
    }
    
    // If student role is selected, validate student-specific fields
    if ($role_id == "3") { // Assuming 3 is student role_id
        // Validate student ID
        if (empty($_POST["student_id"])) {
            $errors[] = "Student ID is required";
        } else {
            $student_id = test_input($_POST["student_id"]);
            
            // Check if student ID already exists
            $student_id_check_query = "SELECT * FROM users WHERE student_id = ?";
            $stmt = $conn->prepare($student_id_check_query);
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Student ID already exists";
            }
            $stmt->close();
        }
        
        // Validate department
        if (empty($_POST["department"])) {
            $errors[] = "Department is required for students";
        } else {
            $department = test_input($_POST["department"]);
        }
        
        // Validate year of study
        if (empty($_POST["year_of_study"])) {
            $errors[] = "Year of study is required for students";
        } else {
            $year_of_study = test_input($_POST["year_of_study"]);
        }
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare and execute the SQL query
        $sql = "INSERT INTO users (role_id, first_name, last_name, email, password_hash, student_id, department, year_of_study) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        // If not a student, set student-specific fields to NULL
        if ($role_id != "3") {
            $student_id = null;
            $year_of_study = null;
        }
        
        $stmt->bind_param("issssssi", $role_id, $first_name, $last_name, $email, $password_hash, $student_id, $department, $year_of_study);
        
        if ($stmt->execute()) {
            $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
            // Clear form data after successful submission
            $first_name = $last_name = $email = $password = $confirm_password = "";
            $student_id = $department = $year_of_study = "";
            $role_id = "";
        } else {
            $errors[] = "Error: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Function to sanitize input data
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENSA Project Management - Registration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background-image: url('../image/Capture.PNG'); /* Path to your image */
            background-size: cover; /* Ensure the image covers the entire screen */
            background-position: center; /* Center the image */
            background-repeat: no-repeat; /* Prevent the image from repeating */
            height: 100vh; /* Full viewport height */
        }
        .registration-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .form-title {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .student-fields {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container registration-container">
        <h2 class="form-title">Create an Account</h2>
        <h5 class="text-muted text-center mb-4">ENSA Student Project Management System</h5>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $first_name; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $last_name; ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Password must be at least 8 characters long.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="role_id" class="form-label">Role</label>
                <select class="form-select" id="role_id" name="role_id" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['role_id']; ?>" <?php if ($role_id == $role['role_id']) echo "selected"; ?>>
                            <?php echo $role['role_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="student-fields" class="student-fields">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="student_id" class="form-label">Student ID</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" value="<?php echo $student_id; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="year_of_study" class="form-label">Year of Study</label>
                        <select class="form-select" id="year_of_study" name="year_of_study">
                            <option value="">Select Year</option>
                            <option value="3" <?php if ($year_of_study == "3") echo "selected"; ?>>3rd Year</option>
                            <option value="4" <?php if ($year_of_study == "4") echo "selected"; ?>>4th Year</option>
                            <option value="5" <?php if ($year_of_study == "5") echo "selected"; ?>>5th Year</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="department" class="form-label">Department</label>
                    <select class="form-select" id="department" name="department">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php if ($department == $dept) echo "selected"; ?>>
                                <?php echo $dept; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>
            
            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </form>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Show/hide student fields based on role selection
        $(document).ready(function() {
            // Check initial value
            if ($("#role_id").val() == "1") {
                $("#student-fields").show();
            }
            
            // On change
            $("#role_id").change(function() {
                if ($(this).val() == "1") { // If student role is selected
                    $("#student-fields").show();
                } else {
                    $("#student-fields").hide();
                }
            });
        });
    </script>
</body>
</html>