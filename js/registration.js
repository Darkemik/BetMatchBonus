let email;
let password;

document.getElementById("regisztracio").onclick = function() {
    email = document.getElementById("email").value;
    password = document.getElementById("password").value;
    console.log(email);
    console.log(password);
}
