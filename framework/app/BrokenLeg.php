<?php
namespace BitsTheater;
use BitsTheater\costumes\Wardrobe\BrokenLeg as BaseException;
{ //begin namespace

/**
 * Provides a standardized way to design a custom exception that can use the
 * BitsTheater text resources (which can be translated to multiple languages) as
 * the basis of the exception's message. Some standard error messages are
 * defined here, corresponding to general-purpose error messages in the
 * BitsGeneric resource.
 *
 * A consumer of this class would call the static toss() method, passing in a
 * resource context (actor, model, or scene), a semantic exception tag, and
 * (optionally) additional data that is part of the corresponding text message
 * resource.
 *
 * The class is self-sufficient for generating standard exceptions; to extend
 * it, your custom exception class need only provide additional constants with
 * names following the covention of "ERR_tag" and "MSG_tag", where the "ERR_"
 * constant is a numeric code, and the "MSG_" tag refers to a translated text
 * resource name. Neither the code nor the message need be unique; several error
 * scenarios could mapped to a common code or to a common message. Only the tag
 * used to choose the exception condition need be unique, and that uniqueness is
 * enforced by making it the name of the constant in the class definition.
 *
 * The class also provides mnemonic constants for a selection of HTTP error
 * codes, so that the numeric constants for errors can be more obviously tied to
 * those standard codes.
 */
class BrokenLeg extends BaseException
{
	//nothing to override, yet
	
} //end class

} //end namespace
