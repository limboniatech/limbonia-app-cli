<?php
namespace Limbonia\Traits\Controller\Cli;

/**
 * Limbonia ModelController Trait
 *
 * This trait allows an inheriting controller to use an model
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
trait HasModel
{
  /**
   * Initialize this controller's custom data, if there is any
   *
   * @throws \Limbonia\Exception
   */
  protected function cliModelInit()
  {
  }
}
