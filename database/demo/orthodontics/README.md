# Orthodontics demo assets

Used only by `php artisan orthodontics:demo`, which builds a demonstration case. None of this is patient data from a Smart Clinic clinic.

| File | What it is | Source and licence |
|---|---|---|
| `ceph_lateral.jpg` | A lateral cephalogram with a millimetre ruler (top right). | "Cephalometric radiograph.JPG" by ANUG, [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/), from [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Cephalometric_radiograph.JPG). The file is renamed but otherwise unmodified. |
| `model_upper.stl`, `model_lower.stl` | Synthetic arch-shaped meshes with tooth bumps, in millimetres. | Generated for Smart Clinic. They are not scans of a real patient. |
| `cbct_phantom.zip` | A synthetic CBCT phantom: 80 DICOM slices of 128×128 pixels, with 0.4 mm voxels. It contains a horseshoe-shaped "jaw" with "teeth", and the patient name is `SYNTHETIC^PHANTOM`. | Generated for Smart Clinic. It contains no patient data. |

The radiograph's landmarks in the command were placed by eye for demonstration. They aren't a clinical tracing, and the values they produce aren't a diagnosis.
