<?php
/* Template Name: Calculator */
get_header();

$results = '';
$material = isset($_POST['material']) ? $_POST['material'] : 'carbon';
$faceLength = isset($_POST['faceLength']) ? $_POST['faceLength'] : '';
$rollerDiameter = isset($_POST['rollerDiameter']) ? $_POST['rollerDiameter'] : '';
$wallThickness = isset($_POST['wallThickness']) ? $_POST['wallThickness'] : '';
$webTension = isset($_POST['webTension']) ? $_POST['webTension'] : '';
$wrapAngle = isset($_POST['wrapAngle']) ? $_POST['wrapAngle'] : '';

if(isset($_POST['material']))
{
    $density = array('carbon' => 0.05, 'aluminum' => 0.1, 'steel' => 0.3);
    $modulus = array('carbon' => 15000000, 'aluminum' => 10000000, 'steel' => 32000000);
    
    //calculate Mass Moment of Inertia (lb-ft^2) (meaures the extent to which an object resists acceleration about an axis)
    $inertia = .0000682 * $density[$material] * $faceLength * (pow($rollerDiameter, 4) - pow($rollerDiameter - 2 * $wallThickness, 4));
    
    //calculate Maximum deflection (inches)
    $load = 2 * sin(0.5 * $wrapAngle) * $webTension;
    $moment = (pi() / 64) * (pow($rollerDiameter, 4) - pow($rollerDiameter - 2 * $wallThickness, 4));
    $deflection = (5 * $load * pow($faceLength, 4)) / (384 * $modulus[$material] * $moment);
    
    $results = '<div><strong>Mass Moment of Inertia:</strong> ' . $inertia . ' <em>lb-ft&#0178</em></div>
                <div><strong>Maximum Deflection:</strong> ' . $deflection . ' <em>inches</em></div>';
}

?>

<form method="post">
    <div>
        <label for="material"><span class="required">*</span> Material</label>
        <select name="material">
            <option <?php echo $material == 'carbon' ? 'selected' : '' ?> value="carbon">Carbon Fiber</option>
            <option <?php echo $material == 'aluminum' ? 'selected' : '' ?> value="aluminum">Aluminum</option>
            <option <?php echo $material == 'steel' ? 'selected' : '' ?> value="steel">Steel</option>
        </select>
    </div>
    <div>
        <label for="faceLength"><span class="required">*</span> Face Length (inches)</label>
        <input type="text" id="faceLength" name="faceLength" value="<?php echo $faceLength ?>">
    </div>
    <div>
        <label for="rollerDiameter"><span class="required">*</span> Roller Diameter (inches)</label>
        <input type="text" id="rollerDiameter" name="rollerDiameter" value="<?php echo $rollerDiameter ?>">
    </div>
    <div>
        <label for="wallThickness"><span class="required">*</span> Wall Thickness (inches)</label>
        <input type="text" id="wallThickness" name="wallThickness" value="<?php echo $wallThickness ?>">
    </div>
    <div>
        <label for="webTension"><span class="required">*</span> Web Tension (lb/inch)</label>
        <input type="text" id="webTension" name="webTension" value="<?php echo $webTension ?>">
    </div>
    <div>
        <label for="wrapAngle"><span class="required">*</span> Wrap Angle (degrees)</label>
        <input type="text" id="wrapAngle" name="wrapAngle" value="<?php echo $wrapAngle ?>">
    </div>
    <button type="submit">Submit</button>
</form>
<div id="results">
    <?php echo $results ?>
</div>

<?php get_footer() ?>