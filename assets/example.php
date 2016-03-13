<!DOCTYPE html>
<meta charset="UTF-8">
<title>Example</title>
<h1>Hello, World</h1>
<p>
  <img src="yj.jpg" width="200">
  <img src="mur.jpg" width="200">
  <img src="kmr.jpg" width="200">
</p>
<p>
  Current DateTime is <?=(new DateTime('now Asia/Tokyo'))->format('Y/m/d H:i:s')?><br>
</p>
<h1>Submit as GET parameters</h1>
<form action="" method="get">
    A: <input type="text" name="A" value=""><br>
    B: <input type="text" name="B" value=""><br>
    <input type="submit">
    <pre><?=htmlspecialchars(var_export($_GET, true), ENT_QUOTES, 'UTF-8')?></pre>
</form>
<h1>Submit as POST parameters</h1>
<form action="" method="post">
    A: <input type="text" name="A" value=""><br>
    B: <input type="text" name="B" value=""><br>
    <input type="submit">
    <pre><?=htmlspecialchars(var_export($_POST, true), ENT_QUOTES, 'UTF-8')?></pre>
</form>
