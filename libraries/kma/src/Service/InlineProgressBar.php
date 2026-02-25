<?php
namespace Kma\Library\Kma\Service;

class InlineProgressBar
{
    protected array $bgClassThresholds;
    public function __construct(array $bgClassThresholds=[])
    {
        if(!empty($bgClassThresholds))
            $this->bgClassThresholds=$bgClassThresholds;
        else
            $this->bgClassThresholds=[
                25 => 'bg-danger',
                50 => 'bg-warning',
                75 => 'bg-info',
                100 => 'bg-success',
            ];
    }
    protected function getBgClass(float $progress): string
    {
        foreach ($this->bgClassThresholds as $threshold=>$bgClass) {
            if($progress<=$threshold)
                return $bgClass;
        }
        return '';
    }

	/**
	 * @param   float   $progress A float value between 0 and 100 representing the percentage of progress. This value will be used to determine the width of the progress bar and its color based on the defined thresholds.
	 * @param   string  $title A string to be used as the title attribute of the progress bar container. This can provide additional information about the progress when the user hovers over the progress bar.
	 *
	 * @return string An HTML string representing the progress bar.
	 *         The caller should ensure that the returned string is
	 *         rendered as raw HTML (i.e. not escaped) in the view.
	 *
	 * @since 1.0.2
	 */
    public function render(float $progress, string $title):string
    {
        return '<div class="progress"  title="'.$title.'">
                    <span class="progress-bar '.$this->getBgClass($progress).' progress-bar-striped" role="progressbar"
                          aria-valuenow="'.$progress.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$progress.'%">
                          '.$progress.' %
                    </span>
                </div>';
    }
}